<?php

namespace Database\Seeders;

use App\Enums\MailStatus;
use App\Mail\ContactBevestigingMail;
use App\Mail\ContactMessageMail;
use App\Mail\SecurityAlertMail;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\MailLog;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Verzonnen data om de schermen mee te bekijken.
 *
 * **Dit is het enige bestand in `database/seeders` met testdata erin.** Alle
 * andere seeders horen in élke omgeving te draaien -- dat zijn de rollen, het
 * account van de eigenaar, de secties en de echte loopbaan van de klant. Deze
 * hoort daar níet bij en staat daarom bewust **niet** in `DatabaseSeeder`.
 *
 * Draai hem expliciet:
 *
 *     php artisan db:seed --class=VoorbeeldDataSeeder
 *
 * En gooi hem net zo makkelijk weg:
 *
 *     php artisan db:seed --class=VoorbeeldDataSeeder -- --opruimen
 *
 * Dat laatste kan niet via `db:seed`, dus daarvoor is er een vlag op het
 * model: elke rij die hier wordt gemaakt krijgt een herkenbaar e-mailadres
 * op `@voorbeeld.test`. Zie `opruimen()`.
 *
 * **Hij weigert buiten local en testing.** Niet uit voorzichtigheid maar
 * omdat het anders echt misgaat: deze rijen zien er in het portaal
 * precies zo uit als echte aanvragen van echte mensen, en die twee door
 * elkaar laten lopen is onherstelbaar. `DatabaseSeeder` schrijft die regel
 * voor; zie het commentaar daar.
 *
 * Wat je ervan krijgt:
 *
 * - **vijf onderwerpen**, waarvan één offline en één zonder Engelse naam,
 *   zodat je ziet wat er op de site komt en wat niet;
 * - **negen aanvragen** in alle standen die bestaan: ongelezen, gelezen,
 *   beantwoord, met en zonder bedrijf en telefoon, Nederlands en Engels,
 *   een zelf getypt onderwerp, een onderwerp dat later is verwijderd, een
 *   heel lang bericht en een heel kort;
 * - **zeven regels in het mailoverzicht**, inclusief een bounce en een
 *   mislukte verzending, want dat is wat je wil kunnen zien.
 */
class VoorbeeldDataSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Het domein waarmee verzonnen rijen te herkennen zijn.
     *
     * `.test` is door de IANA gereserveerd en bestaat dus nooit echt. Een
     * mail hierheen kan niet per ongeluk bij iemand aankomen.
     */
    private const DOMEIN = '@voorbeeld.test';

    /**
     * Zet de verzonnen data neer.
     *
     * **Deze seeder zegt niets.** Dat is geen karigheid: een seeder heeft
     * alleen een terminal als hij via `db:seed` is aangeroepen, en dat is
     * niet te zien aan de typen -- `Seeder::$command` is volgens Laravel
     * nooit leeg, dus zowel `?->` als `isset()` wordt door de statische
     * analyse afgekeurd, terwijl hij in werkelijkheid wél leeg is bij een
     * rechtstreekse aanroep.
     *
     * De uitweg is niet een slimmere controle maar een andere verdeling:
     * `voorbeeld:zaaien` doet het praten en deze klasse het werk. Dat is
     * bovendien symmetrisch met `voorbeeld:opruimen`.
     */
    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException(
                'VoorbeeldDataSeeder draait alleen in local en testing. '
                    .'Deze rijen zijn niet van echte mensen te onderscheiden in het portaal.'
            );
        }

        $this->aanvragen($this->onderwerpen());
        $this->mailoverzicht();
    }

    /**
     * De onderwerpen waaruit een bezoeker kan kiezen.
     *
     * @return array<string, ContactSubject>
     */
    private function onderwerpen(): array
    {
        // naam-nl, naam-en, online, plek, uitgelicht
        $rijen = [
            'gesprek' => ['Vrijblijvend gesprek', 'Informal conversation', true, 1, false],
            'offerte' => ['Offerte aanvragen', 'Request a quote', true, 2, false],

            // Uitgelicht: komt als snelkeuze bóven de keuzelijst. Spoed is
            // het geval waarvoor dat bestaat -- wie een storing heeft wil
            // niet eerst een lijst openklappen.
            'storing' => ['Storing of spoed', 'Outage or urgent', true, 3, true],

            // Zonder Engelse naam: op de Engelse site valt hij terug op het
            // Nederlands. Zo zie je dat gedrag zonder het te hoeven
            // uitproberen.
            'samenwerking' => ['Samenwerken', null, true, 4, false],

            // En één die offline staat: die hoort niet op de site te komen.
            'vacature' => ['Vacature of stage', 'Vacancy or internship', false, 5, false],
        ];

        $gemaakt = [];

        foreach ($rijen as $sleutel => [$nl, $en, $online, $plek, $uitgelicht]) {
            $gemaakt[$sleutel] = ContactSubject::query()->updateOrCreate(
                ['label_nl' => $nl],
                [
                    'label_en' => $en,
                    'published' => $online,
                    'position' => $plek,
                    'featured' => $uitgelicht,
                ],
            );
        }

        return $gemaakt;
    }

    /**
     * De aanvragen, in elke stand die het scherm kent.
     *
     * @param  array<string, ContactSubject>  $onderwerpen
     */
    private function aanvragen(array $onderwerpen): void
    {
        $compleet = ['name', 'email', 'company', 'phone', 'subject', 'message'];
        $standaard = ['name', 'email', 'subject', 'message'];

        /*
         * Elk met een eigen tijdstip, want het scherm sorteert op
         * binnenkomst en een rij van negen gelijke tijdstempels zegt niets
         * over de volgorde.
         */
        $rijen = [
            // Vers en ongelezen: dit is wat de eigenaar als eerste ziet.
            [
                'naam' => 'Sanne de Boer',
                'onderwerp' => $onderwerpen['offerte'],
                'bericht' => 'We zoeken iemand die onze werkplekken en ons netwerk onder handen neemt. Een kantoor met twaalf mensen, nu alles via een losse laptopbeheerder. Kun je een indicatie geven van wat zo'."'".'n overstap kost?',
                'bedrijf' => 'De Boer Interieurbouw',
                'telefoon' => '06 24 55 10 92',
                'velden' => $compleet,
                'uren' => 2,
            ],
            [
                'naam' => 'Youssef el Amrani',
                'onderwerp' => $onderwerpen['storing'],
                'bericht' => 'Onze mail doet het sinds vanmorgen niet meer. Niemand kan erbij. Kun je bellen?',
                'telefoon' => '06 11 87 44 03',
                'velden' => ['name', 'email', 'phone', 'subject', 'message'],
                'uren' => 6,
            ],

            // Gelezen maar nog niet beantwoord.
            [
                'naam' => 'Marieke Visser',
                'onderwerp' => $onderwerpen['gesprek'],
                'bericht' => 'Ik werd op je doorverwezen door een collega. Zou je een half uur hebben om mee te denken over onze overstap naar Microsoft 365?',
                'bedrijf' => 'Praktijk Visser',
                'velden' => ['name', 'email', 'company', 'subject', 'message'],
                'gelezen' => true,
                'uren' => 26,
            ],

            // Een Engelstalige bezoeker. Zijn bevestiging was dus Engels.
            [
                'naam' => 'Daniel Okafor',
                'onderwerp' => $onderwerpen['samenwerking'],
                'bericht' => 'We are a small agency in Rotterdam looking for a partner to handle IT for our clients. Would you be open to a conversation about working together?',
                'bedrijf' => 'Northbound Studio',
                'taal' => 'en',
                'velden' => ['name', 'email', 'company', 'subject', 'message'],
                'gelezen' => true,
                'uren' => 30,
            ],

            // Beantwoord: beide bollen aan.
            [
                'naam' => 'Peter Hoekstra',
                'onderwerp' => $onderwerpen['offerte'],
                'bericht' => 'Graag een voorstel voor het beheer van onze zes werkplekken, inclusief back-up.',
                'bedrijf' => 'Hoekstra Transport',
                'telefoon' => '0512 - 54 19 83',
                'velden' => $compleet,
                'beantwoord' => true,
                'uren' => 52,
            ],
            [
                'naam' => 'Fatima Yildiz',
                'onderwerp' => $onderwerpen['gesprek'],
                'bericht' => 'Dank voor het gesprek van vorige week. Ik stuur de gegevens die je vroeg nog na.',
                'velden' => $standaard,
                'beantwoord' => true,
                'uren' => 96,
            ],

            // Een zelf getypt onderwerp: de bezoeker koos niet uit de lijst.
            [
                'naam' => 'Rob Mulder',
                'eigenOnderwerp' => 'Vraag over een oude factuur',
                'bericht' => 'Ik kan factuur 2024-118 niet meer vinden in mijn administratie. Kun je die opnieuw sturen?',
                'velden' => $standaard,
                'gelezen' => true,
                'uren' => 150,
            ],

            // Een heel lang bericht: zo zie je of het scherm dat aankan.
            [
                'naam' => 'Ingrid van Dijk',
                'onderwerp' => $onderwerpen['samenwerking'],
                'bericht' => trim(str_repeat(
                    'We zijn aan het nadenken over hoe we onze IT de komende jaren willen inrichten, en ik merk dat ik daar graag eens uitgebreid over zou willen sparren met iemand die er vaker mee te maken heeft. ',
                    6,
                )),
                'bedrijf' => 'Van Dijk & Partners',
                'telefoon' => '06 38 20 71 45',
                'velden' => $compleet,
                'uren' => 200,
            ],

            // En een heel kort, met een onderwerp dat later is verwijderd.
            [
                'naam' => 'Tom Beekman',
                'eigenOnderwerp' => 'Terugbelverzoek',
                'bericht' => 'Kun je me bellen? Alvast dank.',
                'telefoon' => '06 49 03 66 21',
                'velden' => ['name', 'email', 'phone', 'subject', 'message'],
                'verdwenenOnderwerp' => true,
                'beantwoord' => true,
                'uren' => 400,
            ],
        ];

        foreach ($rijen as $rij) {
            $moment = now()->subHours($rij['uren']);

            $onderwerp = $rij['onderwerp'] ?? null;

            $aanvraag = ContactSubmission::query()->updateOrCreate(
                ['email' => $this->adres($rij['naam'])],
                [
                    'locale' => $rij['taal'] ?? 'nl',
                    'name' => $rij['naam'],
                    'company' => $rij['bedrijf'] ?? null,
                    'phone' => $rij['telefoon'] ?? null,

                    /*
                     * Een verdwenen onderwerp: de tekst blijft, de
                     * verwijzing niet. Zo ziet de eigenaar de melding
                     * "dit onderwerp bestaat niet meer" zonder dat hij er
                     * eerst een hoeft weg te gooien.
                     */
                    'subject_id' => ($rij['verdwenenOnderwerp'] ?? false)
                        ? null
                        : $onderwerp?->id,

                    'subject_text' => $rij['eigenOnderwerp']
                        ?? $onderwerp?->naam()
                        ?? 'Onbekend',

                    'subject_custom' => isset($rij['eigenOnderwerp'])
                        && ! ($rij['verdwenenOnderwerp'] ?? false),

                    'message' => $rij['bericht'],
                    'shown' => $rij['velden'],

                    'read_at' => ($rij['gelezen'] ?? false) || ($rij['beantwoord'] ?? false)
                        ? $moment->copy()->addHours(1)
                        : null,

                    'answered_at' => ($rij['beantwoord'] ?? false)
                        ? $moment->copy()->addHours(2)
                        : null,

                    'created_at' => $moment,
                    'updated_at' => $moment,
                ],
            );

            /*
             * `created_at` gaat niet mee via `updateOrCreate` als de rij al
             * bestond, dus hier nog een keer. Anders staan ze bij een
             * tweede keer zaaien allemaal op vandaag.
             */
            $aanvraag->forceFill([
                'created_at' => $moment,
                'updated_at' => $moment,
            ])->saveQuietly();
        }
    }

    /**
     * Het mailoverzicht, met de gevallen die je wil kunnen zien.
     *
     * Niet alleen geslaagde mail: een bounce en een mislukte verzending
     * zijn precies waarvoor dat scherm bestaat.
     */
    private function mailoverzicht(): void
    {
        $rijen = [
            [
                'mailable' => ContactMessageMail::class,
                'subject' => ContactMessageMail::logboekOnderwerp(),
                'to' => [config('mail.contact_address')],
                'status' => MailStatus::Delivered,
                'uren' => 2,
            ],
            [
                'mailable' => ContactBevestigingMail::class,
                'subject' => ContactBevestigingMail::logboekOnderwerp(),
                'to' => [$this->adres('Sanne de Boer')],
                'status' => MailStatus::Opened,
                'uren' => 2,
            ],
            [
                'mailable' => ContactMessageMail::class,
                'subject' => ContactMessageMail::logboekOnderwerp(),
                'to' => [config('mail.contact_address')],
                'status' => MailStatus::Delivered,
                'uren' => 6,
            ],
            [
                'mailable' => ContactBevestigingMail::class,
                'subject' => ContactBevestigingMail::logboekOnderwerp(),
                'to' => [$this->adres('Youssef el Amrani')],
                'status' => MailStatus::Sent,
                'uren' => 6,
            ],

            // Een adres dat niet bestaat: de provider stuurt hem terug.
            [
                'mailable' => ContactBevestigingMail::class,
                'subject' => ContactBevestigingMail::logboekOnderwerp(),
                'to' => ['typefout'.self::DOMEIN],
                'status' => MailStatus::Bounced,
                'error' => '550 5.1.1 The email account that you tried to reach does not exist.',
                'uren' => 30,
            ],

            // En een die nooit is vertrokken; zie MeldtMislukteVerzending.
            [
                'mailable' => ContactBevestigingMail::class,
                'subject' => ContactBevestigingMail::logboekOnderwerp(),
                'to' => [$this->adres('Rob Mulder')],
                'status' => MailStatus::Failed,
                'error' => 'Symfony\Component\Mailer\Exception\TransportException: Connection could not be established with host "smtp:587".',
                'uren' => 150,
                'nietVerstuurd' => true,
            ],

            [
                'mailable' => SecurityAlertMail::class,
                'subject' => 'Beveiligingsmelding: Problemen met uitgaande mail',
                'to' => [config('security.alerts.address') ?? config('mail.contact_address')],
                'status' => MailStatus::Delivered,
                'uren' => 149,
            ],
        ];

        foreach ($rijen as $rij) {
            $moment = now()->subHours($rij['uren']);

            MailLog::query()->updateOrCreate(
                ['message_id' => 'voorbeeld-'.md5((string) json_encode($rij))],
                [
                    'mailable' => $rij['mailable'],
                    'mailer' => config('mail.default'),
                    'subject' => $rij['subject'],
                    'to' => $rij['to'],
                    'status' => $rij['status'],
                    'error' => $rij['error'] ?? null,

                    // Een mail die nooit vertrok heeft geen verzendmoment.
                    'sent_at' => ($rij['nietVerstuurd'] ?? false) ? null : $moment,

                    'last_event_at' => $moment,
                    'created_at' => $moment,
                    'updated_at' => $moment,
                ],
            );
        }
    }

    /**
     * Een herkenbaar adres bij een naam.
     *
     * Altijd op `@voorbeeld.test`, zodat `voorbeeld:opruimen` precies weet
     * wat van hem is en er nooit een echte aanvraag per ongeluk meegaat.
     */
    private function adres(string $naam): string
    {
        $kaal = str_replace(' ', '.', mb_strtolower($naam));

        return preg_replace('/[^a-z0-9.]/', '', $kaal).self::DOMEIN;
    }
}
