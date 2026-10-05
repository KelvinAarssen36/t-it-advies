<?php

namespace App\Concerns;

use App\Enums\MailStatus;
use App\Models\MailLog;
use Throwable;

/**
 * Laat een mail die nooit is verstuurd een spoor achter in het mailoverzicht.
 *
 * **Dit vult een gat dat je alleen merkt als het misgaat.**
 * `RecordOutgoingMail` luistert naar `MessageSent`, en dat event komt er
 * alleen als de mail de deur uit is. Ligt de mailserver eruit, dan gooit de
 * job, wordt hij opnieuw geprobeerd, en belandt hij uiteindelijk in
 * `failed_jobs` -- zonder één regel in Beheer → Mail en zonder melding. De
 * eigenaar zag dan een aanvraag in zijn portaal staan waar nooit een mail
 * bij is gekomen, en niets vertelde hem dat.
 *
 * Laravel heeft hier een haakje voor: `SendQueuedMailable::failed()` roept
 * `failed()` op de mailable aan als die methode bestaat. Deze trait is die
 * methode.
 *
 * Een rij met `MailStatus::Failed` wordt door `AnomalyScanner` als
 * mailprobleem geteld, dus de eigenaar krijgt er binnen het uur ook een
 * melding over. Dat is het echte doel: niet het logboek, maar dat iemand
 * het weet.
 *
 * **Niet op `SecurityAlertMail`, en dat is geen vergetelheid.** Die mail
 * is zelf de melding over mailproblemen. Zou hij bij mislukken een rij
 * schrijven, dan is dat een nieuw mailprobleem, waarover de scanner weer
 * een melding wil sturen, die weer kan mislukken. De afkoeltijd houdt dat
 * klein, maar een kringetje blijft een kringetje. Loopt die mail mis, dan
 * staat hij in `failed_jobs`, en dat is voor een alarmeringsmail de juiste
 * plek.
 *
 * Zie docs/architecture/mail-en-queues.md.
 */
trait MeldtMislukteVerzending
{
    /**
     * Hoeveel tekens van de foutmelding we bewaren.
     *
     * Ruim genoeg voor de SMTP-regel waar het antwoord van de server in
     * staat, en krap genoeg dat er geen halve stacktrace in de database
     * belandt.
     */
    private const FOUT_MAXIMUM = 500;

    /**
     * Wat er van deze mail in het logboek mag komen.
     *
     * **Een eis en geen vraag.** Dit stond eerst als `method_exists()` in
     * `failed()` met een terugval op `null`, en PHPStan wees erop dat die
     * tak onbereikbaar is -- allebei de mailables die deze trait gebruiken
     * hebben de methode. Dat is niet toevallig: een mail die de moeite
     * waard is om een mislukking van te loggen draagt inhoud van een
     * bezoeker, en dan is "wat mag hiervan in onze database" een vraag die
     * je niet mag overslaan.
     *
     * Als abstracte methode kan dat ook niet meer: een nieuwe mailable die
     * deze trait gebruikt zonder een neutraal onderwerp op te geven
     * compileert niet.
     */
    abstract public static function logboekOnderwerp(): string;

    public function failed(Throwable $fout): void
    {
        MailLog::create([
            /*
             * Geen Message-ID: dat kent Symfony pas als de mail echt is
             * aangeboden. Een webhook van de provider kan deze rij dus ook
             * nooit bijwerken, en dat hoeft ook niet -- de provider heeft
             * hem nooit gezien.
             */
            'message_id' => null,

            'mailable' => static::class,
            'mailer' => config('mail.default'),

            /*
             * Hetzelfde neutrale onderwerp als bij een geslaagde mail; zie
             * `RecordOutgoingMail::onderwerp()`. Bij een mail die van een
             * bezoeker komt staat in het echte onderwerp tekst die hij zelf
             * heeft getypt, en die hoort niet in onze database -- ook niet
             * als het misging.
             */
            'subject' => static::logboekOnderwerp(),

            'to' => $this->ontvangers(),
            'status' => MailStatus::Failed,
            'error' => $this->foutregel($fout),

            /*
             * `last_event_at` en niet `sent_at`: er is niets verstuurd. De
             * anomaliescanner kijkt naar deze kolom én naar `created_at`,
             * dus de melding komt er hoe dan ook.
             */
            'last_event_at' => now(),
        ]);
    }

    /**
     * De adressen waar deze mail naartoe had moeten gaan.
     *
     * @return array<int, string>
     */
    private function ontvangers(): array
    {
        /** @var array<int, array{address?: string}> $to */
        $to = $this->to;

        return array_values(array_filter(array_map(
            fn (array $adres) => $adres['address'] ?? null,
            $to,
        )));
    }

    /**
     * De foutmelding, ingekort.
     *
     * De klasse erbij, want "Connection could not be established" zegt iets
     * anders als het een `TransportException` is dan wanneer het een
     * `RuntimeException` uit onze eigen code is.
     *
     * **Geen stacktrace en geen inhoud van de mail.** Symfony zet in zijn
     * foutmeldingen het antwoord van de mailserver en het adres van de
     * ontvanger, en dat is precies wat je wil weten; het bericht zelf komt
     * er niet in voor. Mocht een provider ooit iets gevoeligers
     * terugsturen, dan helpt deze grens de schade beperken -- maar de echte
     * regel blijft dat we niets loggen wat we niet nodig hebben.
     */
    private function foutregel(Throwable $fout): string
    {
        $regel = $fout::class.': '.$fout->getMessage();

        return mb_strimwidth($regel, 0, self::FOUT_MAXIMUM, '...');
    }
}
