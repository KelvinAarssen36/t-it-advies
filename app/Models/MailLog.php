<?php

namespace App\Models;

use App\Enums\MailStatus;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Een verstuurde mail, plus de statusgeschiedenis die de mailprovider via
 * webhooks terugmeldt.
 *
 * We bewaren bewust geen mailinhoud, alleen wie, wat en wanneer. Wil je de
 * inhoud kunnen terugzien, doe dat dan expliciet en met een bewaartermijn.
 * Zie docs/architecture/mail-en-queues.md.
 *
 * @property int $id
 * @property string|null $message_id
 * @property string|null $mailable
 * @property string|null $mailer
 * @property string|null $subject
 * @property array<int, string> $to
 * @property array<int, string>|null $cc
 * @property array<int, string>|null $bcc
 * @property MailStatus $status
 * @property string|null $error
 * @property array<int, array<string, mixed>>|null $events
 * @property CarbonInterface|null $sent_at
 * @property CarbonInterface|null $last_event_at
 */
#[Fillable([
    'message_id', 'mailable', 'mailer', 'subject',
    'to', 'cc', 'bcc', 'status', 'error', 'events',
    'sent_at', 'last_event_at',
])]
class MailLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'to' => 'array',
            'cc' => 'array',
            'bcc' => 'array',
            'events' => 'array',
            'status' => MailStatus::class,
            'sent_at' => 'datetime',
            'last_event_at' => 'datetime',
        ];
    }

    /**
     * Voegt een provider-event toe aan de tijdlijn en werkt de status bij.
     *
     * De status gaat alleen vooruit in de levensloop van een mail: een laat
     * binnenkomend delivered-event mag een eerdere bounce niet wegpoetsen.
     *
     * @param  array<string, mixed>  $payload
     */
    public function recordProviderEvent(MailStatus $status, array $payload = [], ?CarbonInterface $occurredAt = null): void
    {
        $occurredAt ??= now();

        $this->events = [...($this->events ?? []), [
            'status' => $status->value,
            'occurred_at' => $occurredAt->toIso8601String(),
            'payload' => $payload,
        ]];

        if ($status->outranks($this->status)) {
            $this->status = $status;
        }

        $this->last_event_at = $occurredAt;
        $this->save();
    }
}
