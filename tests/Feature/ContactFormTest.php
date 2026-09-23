<?php

namespace Tests\Feature;

use App\Enums\SecurityEventType;
use App\Mail\ContactMessageMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Honeypot\EncryptedTime;
use Tests\TestCase;

/**
 * Het contactformulier is de plek waar de buitenwereld de applicatie raakt.
 * Deze tests bewaken de drie lagen: honeypot, validatie en de queue.
 */
class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, string>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Kees Jansen',
            'email' => 'kees@example.com',
            'subject' => 'Vraag over een migratie',
            'message' => 'Graag advies over onze overstap naar een nieuwe omgeving.',
            config('honeypot.name_field_name') => '',
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->subMinute()),
        ], $overrides);
    }

    public function test_a_valid_message_is_queued(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->validPayload())
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        Mail::assertQueued(ContactMessageMail::class, function (ContactMessageMail $mail) {
            return $mail->senderEmail === 'kees@example.com'
                && $mail->hasTo(config('mail.contact_address'));
        });
    }

    public function test_the_mail_is_queued_and_not_sent_synchronously(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->validPayload());

        // Wachten op de mailprovider mag een bezoeker nooit ophouden.
        Mail::assertNothingSent();
        Mail::assertQueued(ContactMessageMail::class);
    }

    public function test_it_validates_the_input(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->validPayload([
            'email' => 'geen-adres',
            'message' => 'kort',
        ]))->assertSessionHasErrors(['email', 'message']);

        Mail::assertNothingQueued();
    }

    public function test_a_filled_honeypot_blocks_the_message_and_is_logged(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->validPayload([
            config('honeypot.name_field_name') => 'ik-ben-een-bot',
        ]))->assertRedirect();

        Mail::assertNothingQueued();

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::SpamBlocked->value,
        ]);
    }

    public function test_a_message_submitted_too_fast_is_blocked(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->validPayload([
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->addMinute()),
        ]))->assertRedirect();

        Mail::assertNothingQueued();
    }

    public function test_it_is_rate_limited(): void
    {
        Mail::fake();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $this->post(route('contact.store'), $this->validPayload());
        }

        $this->post(route('contact.store'), $this->validPayload())
            ->assertStatus(429);
    }
}
