<?php

namespace Tests\Feature\Mail;

use App\Enums\MailStatus;
use App\Enums\SecurityEventType;
use App\Mail\ContactMessageMail;
use App\Models\MailLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Wat er verstuurd wordt moet terug te zien zijn, en wat de provider
 * terugmeldt moet daarop aansluiten. De webhook is een openbaar endpoint,
 * dus die moet ook echt dicht zitten zonder geldige handtekening.
 */
class MailLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_sent_mail_is_recorded(): void
    {
        Mail::to('klant@example.com')->send(new ContactMessageMail(
            senderName: 'Kees',
            senderEmail: 'kees@example.com',
            senderSubject: 'Hallo',
            body: 'Een bericht.',
        ));

        $log = MailLog::sole();

        $this->assertSame(['klant@example.com'], $log->to);
        $this->assertSame(MailStatus::Sent, $log->status);
        $this->assertNotNull($log->message_id);
        $this->assertNotNull($log->subject);
    }

    /**
     * Van een bericht van een bezoeker komt er geen tekst in het logboek.
     *
     * **Dit is een belofte uit de privacyverklaring en geen voorkeur.**
     * Daar staat dat we een logboek bijhouden met het tijdstip, de
     * ontvanger en de status -- "niet je bericht en niet je onderwerp".
     *
     * Het echte onderwerp van de mail blijft wél volledig: de eigenaar ziet
     * in zijn postvak "Contactformulier: Hallo". Dit gaat alleen over wat
     * wíj honderd tachtig dagen in onze database bewaren.
     *
     * Deze test stond er eerst omgekeerd in: hij controleerde dat het
     * onderwerp van de bezoeker wél in het logboek stond. Dat was precies
     * de onwaarheid die uit de verklaring moest.
     *
     * Zie ContactMessageMail::logboekOnderwerp() en RecordOutgoingMail.
     */
    public function test_a_visitors_own_words_do_not_end_up_in_the_log(): void
    {
        Mail::to('klant@example.com')->send(new ContactMessageMail(
            senderName: 'Kees',
            senderEmail: 'kees@example.com',
            senderSubject: 'Mijn dochter is ziek, kan het later',
            body: 'Een bericht met gevoelige inhoud.',
        ));

        $log = MailLog::sole();

        // Niets uit het onderwerp van de bezoeker.
        $this->assertStringNotContainsString('dochter', (string) $log->subject);
        $this->assertStringNotContainsString('ziek', (string) $log->subject);

        // En het hele logboek bevat geen letter van zijn bericht.
        $alles = (string) json_encode($log->toArray());
        $this->assertStringNotContainsString('gevoelige inhoud', $alles);

        // Wel genoeg om terug te vinden wat er is verstuurd.
        $this->assertSame(['klant@example.com'], $log->to);
        $this->assertStringContainsString('ontactformulier', (string) $log->subject);
    }

    /** Een gewone mail houdt zijn echte onderwerp in het logboek. */
    public function test_an_ordinary_mail_keeps_its_subject_in_the_log(): void
    {
        Mail::raw('Tekst', function ($bericht) {
            $bericht->to('klant@example.com')->subject('Gewoon onderwerp');
        });

        $this->assertSame('Gewoon onderwerp', MailLog::sole()->subject);
    }

    public function test_a_provider_event_updates_the_status(): void
    {
        $log = MailLog::create([
            'message_id' => 'abc-123',
            'to' => ['klant@example.com'],
            'status' => MailStatus::Sent,
            'sent_at' => now(),
        ]);

        $log->recordProviderEvent(MailStatus::Delivered);

        $this->assertSame(MailStatus::Delivered, $log->fresh()->status);
        $this->assertCount(1, $log->fresh()->events);
    }

    public function test_a_late_delivered_event_does_not_hide_a_bounce(): void
    {
        $log = MailLog::create([
            'message_id' => 'abc-124',
            'to' => ['klant@example.com'],
            'status' => MailStatus::Sent,
            'sent_at' => now(),
        ]);

        $log->recordProviderEvent(MailStatus::Bounced);
        $log->recordProviderEvent(MailStatus::Delivered);

        $this->assertSame(MailStatus::Bounced, $log->fresh()->status);
        $this->assertCount(2, $log->fresh()->events, 'De tijdlijn bewaart wel beide events.');
    }

    public function test_the_webhook_refuses_a_request_without_a_valid_signature(): void
    {
        config(['services.resend.webhook_secret' => 'whsec_'.base64_encode('geheim')]);

        $this->postJson(route('webhooks.resend'), ['type' => 'email.delivered'])
            ->assertStatus(401);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::WebhookRejected->value,
        ]);
    }

    public function test_the_webhook_refuses_everything_when_no_secret_is_configured(): void
    {
        config(['services.resend.webhook_secret' => null]);

        $this->postJson(route('webhooks.resend'), ['type' => 'email.delivered'])
            ->assertStatus(401);
    }

    public function test_the_webhook_records_a_correctly_signed_event(): void
    {
        $key = random_bytes(24);
        config(['services.resend.webhook_secret' => 'whsec_'.base64_encode($key)]);

        $log = MailLog::create([
            'message_id' => 'resend-id-1',
            'to' => ['klant@example.com'],
            'status' => MailStatus::Sent,
            'sent_at' => now(),
        ]);

        $payload = [
            'type' => 'email.delivered',
            'created_at' => now()->toIso8601String(),
            'data' => ['email_id' => 'resend-id-1'],
        ];

        $body = (string) json_encode($payload);
        $id = 'msg_test';
        $timestamp = (string) time();
        $signature = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

        $this->call(
            'POST',
            route('webhooks.resend'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_SVIX_ID' => $id,
                'HTTP_SVIX_TIMESTAMP' => $timestamp,
                'HTTP_SVIX_SIGNATURE' => "v1,{$signature}",
            ],
            $body,
        )->assertOk();

        $this->assertSame(MailStatus::Delivered, $log->fresh()->status);
    }

    public function test_the_webhook_refuses_a_replayed_request(): void
    {
        $key = random_bytes(24);
        config(['services.resend.webhook_secret' => 'whsec_'.base64_encode($key)]);

        $payload = ['type' => 'email.delivered', 'data' => ['email_id' => 'x']];
        $body = (string) json_encode($payload);
        $id = 'msg_old';
        // Ruim buiten het toegestane tijdvenster.
        $timestamp = (string) (time() - 3600);
        $signature = base64_encode(hash_hmac('sha256', "{$id}.{$timestamp}.{$body}", $key, true));

        $this->call(
            'POST',
            route('webhooks.resend'),
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_SVIX_ID' => $id,
                'HTTP_SVIX_TIMESTAMP' => $timestamp,
                'HTTP_SVIX_SIGNATURE' => "v1,{$signature}",
            ],
            $body,
        )->assertStatus(401);
    }
}
