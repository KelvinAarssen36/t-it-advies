<?php

namespace App\Listeners;

use App\Enums\MailStatus;
use App\Models\MailLog;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;

/**
 * Schrijft elke uitgaande mail weg in `mail_logs`.
 *
 * Dit is de basis van het mailoverzicht in het beveiligde gedeelte. Het
 * Message-ID uit de header is de sleutel waarmee provider-webhooks later de
 * afleverstatus terugkoppelen.
 *
 * We loggen alleen metadata, geen mailinhoud. Zie docs/architecture/mail-en-queues.md.
 */
class RecordOutgoingMail
{
    public function handle(MessageSent $event): void
    {
        $message = $event->message;

        MailLog::create([
            'message_id' => $this->messageId($event),
            'mailable' => $event->data['__laravel_mailable'] ?? null,
            'mailer' => $event->data['__laravel_mailer'] ?? config('mail.default'),
            'subject' => $message->getSubject(),
            'to' => $this->addresses($message->getTo()),
            'cc' => $this->addresses($message->getCc()) ?: null,
            'bcc' => $this->addresses($message->getBcc()) ?: null,
            'status' => MailStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * Het Message-ID zoals de provider het straks terugmeldt, zonder de
     * punthaken waar Symfony het intern in verpakt.
     */
    private function messageId(MessageSent $event): ?string
    {
        $id = trim($event->sent->getMessageId(), '<>');

        return $id !== '' ? $id : null;
    }

    /**
     * @param  array<int, Address>  $addresses
     * @return array<int, string>
     */
    private function addresses(array $addresses): array
    {
        return array_values(array_map(
            fn (Address $address) => $address->getAddress(),
            $addresses,
        ));
    }
}
