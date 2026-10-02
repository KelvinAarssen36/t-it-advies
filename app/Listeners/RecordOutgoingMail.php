<?php

namespace App\Listeners;

use App\Enums\MailStatus;
use App\Models\MailLog;
use Illuminate\Mail\Events\MessageSent;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

/**
 * Schrijft elke uitgaande mail weg in `mail_logs`.
 *
 * Dit is de basis van het mailoverzicht in het beveiligde gedeelte. Het
 * Message-ID uit de header is de sleutel waarmee provider-webhooks later de
 * afleverstatus terugkoppelen.
 *
 * We loggen alleen metadata, geen mailinhoud. Zie docs/architecture/mail-en-queues.md.
 *
 * **Eén nuance op "alleen metadata", en die is er met reden bij gekomen.**
 * De onderwerpregel is metadata, maar bij een mail die van een bezoeker komt
 * staat daar tekst in die hij zelf heeft getypt -- en die zou dan honderd
 * tachtig dagen in onze database staan. Een mailable kan daarom zeggen wat
 * er in het logboek moet komen in plaats van zijn echte onderwerp; zie
 * `logboekOnderwerp()` hieronder en ContactMessageMail.
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
            'subject' => $this->onderwerp($event, $message),
            'to' => $this->addresses($message->getTo()),
            'cc' => $this->addresses($message->getCc()) ?: null,
            'bcc' => $this->addresses($message->getBcc()) ?: null,
            'status' => MailStatus::Sent,
            'sent_at' => now(),
        ]);
    }

    /**
     * Wat er als onderwerp in het logboek komt.
     *
     * Normaal het echte onderwerp -- dat is precies waar je het
     * mailoverzicht voor opent. Maar een mailable die tekst van een
     * bezoeker in zijn onderwerp heeft staan, kan een neutrale variant
     * opgeven met de statische methode `logboekOnderwerp()`. Dan houdt de
     * eigenaar zijn volledige onderwerp in zijn eigen postvak, en staat er
     * in onze database alleen "Contactformulier".
     *
     * **Dit is gegevensminimalisatie en geen netheid.** Het verschil is dat
     * de privacyverklaring nu kan zeggen dat we je bericht niet bewaren,
     * zonder een uitzondering voor de onderwerpregel erbij.
     */
    private function onderwerp(MessageSent $event, Email $message): ?string
    {
        /** @var class-string|null $mailable */
        $mailable = $event->data['__laravel_mailable'] ?? null;

        if (is_string($mailable)
            && class_exists($mailable)
            && method_exists($mailable, 'logboekOnderwerp')) {
            return (string) $mailable::logboekOnderwerp();
        }

        return $message->getSubject();
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
