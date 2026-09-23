<?php

namespace App\Http\Controllers\Webhooks;

use App\Enums\MailStatus;
use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Support\Security\SecurityLogger;
use App\Support\Security\SvixSignature;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Ontvangt afleverstatussen van Resend.
 *
 * Een webhook is een openbaar endpoint, dus de handtekening is hier de
 * enige echte beveiliging. Zonder geldig ondertekeningsgeheim weigeren we
 * alles en leggen we dat vast als beveiligingsgebeurtenis.
 *
 * De route staat bewust buiten de CSRF-middleware (het is geen browser-
 * verzoek) maar wel achter rate limiting.
 *
 * Zie docs/architecture/mail-en-queues.md.
 */
class ResendWebhookController extends Controller
{
    public function __construct(
        private readonly SecurityLogger $logger,
        private readonly SvixSignature $signature,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $secret = (string) config('services.resend.webhook_secret');

        if ($secret === '' || ! $this->signature->verify($request, $secret)) {
            $this->logger->failure(SecurityEventType::WebhookRejected, context: [
                'provider' => 'resend',
                'reason' => $secret === '' ? 'no-secret-configured' : 'invalid-signature',
            ]);

            return response()->json(['message' => 'Invalid signature.'], 401);
        }

        $type = (string) $request->input('type');
        $emailId = $request->string('data.email_id')->toString();
        $status = $this->statusFor($type);

        if ($status === null || $emailId === '') {
            // Onbekend eventtype: bevestigen zodat de provider niet blijft
            // herhalen, maar verder niets doen.
            return response()->json(['message' => 'Ignored.']);
        }

        $log = MailLog::query()->where('message_id', $emailId)->first();

        if ($log === null) {
            return response()->json(['message' => 'Unknown message.']);
        }

        $log->recordProviderEvent(
            $status,
            ['type' => $type],
            $this->occurredAt($request),
        );

        return response()->json(['message' => 'Recorded.']);
    }

    private function statusFor(string $type): ?MailStatus
    {
        return match ($type) {
            'email.sent' => MailStatus::Sent,
            'email.delivered' => MailStatus::Delivered,
            'email.delivery_delayed' => MailStatus::Deferred,
            'email.opened' => MailStatus::Opened,
            'email.clicked' => MailStatus::Clicked,
            'email.bounced' => MailStatus::Bounced,
            'email.complained' => MailStatus::Complained,
            'email.failed' => MailStatus::Failed,
            default => null,
        };
    }

    private function occurredAt(Request $request): CarbonInterface
    {
        $createdAt = $request->input('created_at');

        if (! is_string($createdAt)) {
            return now();
        }

        try {
            return Carbon::parse($createdAt);
        } catch (\Throwable) {
            return now();
        }
    }
}
