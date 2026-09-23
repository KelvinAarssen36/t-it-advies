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
        $this->assertStringContainsString('Hallo', (string) $log->subject);
        $this->assertNotNull($log->message_id);
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
