<?php

namespace App\Console\Commands;

use App\Enums\MailStijl;
use App\Mail\ContactBevestigingMail;
use App\Mail\ContactMessageMail;
use App\Mail\SecurityAlertMail;
use App\Models\ContactSetting;
use App\Models\SiteSetting;
use App\Support\Security\Anomaly;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/**
 * Stuurt een setje testmails, zodat je ze echt in een postbus kunt zien.
 *
 * **Het voorbeeld in het portaal is niet hetzelfde als een echte mail.** Dat
 * rendert de mailable in een `iframe`, en dat is dicht bij de werkelijkheid
 * -- maar een mailprogramma doet nog zijn eigen dingen: het knipt stijlen
 * weg, het herschrijft kleuren, het zet een eigen marge om de inhoud. Dat
 * zie je pas in een echte postbus.
 *
 * Lokaal is dat MailHog of Mailpit op http://localhost:8025; zie
 * docs/development/setup.md.
 *
 * Standaard gaat er van élke mail één in **beide** stijlen, want dat is wat
 * je wil vergelijken. De stijl van de eigenaar wordt daarna teruggezet
 * zoals hij stond -- deze opdracht is om te kijken en niet om iets te
 * veranderen.
 *
 * **Alleen in local en testing.** Dit zijn verzonnen berichten met een
 * verzonnen naam; die horen niet in de postbus van een echte klant.
 */
class StuurTestmails extends Command
{
    protected $signature = 'mail:test
        {--adres= : Waar ze naartoe gaan (standaard het contactadres)}
        {--stijl= : Alleen deze stijl: licht of huisstijl}';

    protected $description = 'Stuurt testmails in beide mailstijlen naar je postbus (alleen lokaal)';

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error(
                'mail:test draait alleen in local en testing.'
            );

            return self::FAILURE;
        }

        $adres = (string) ($this->option('adres') ?: config('mail.contact_address'));

        $stijlen = $this->gekozenStijlen();

        if ($stijlen === []) {
            $this->components->error('Onbekende stijl. Kies licht of huisstijl.');

            return self::FAILURE;
        }

        /*
         * De stand van de eigenaar onthouden. Deze opdracht zet hem
         * tijdelijk om -- de mailables lezen de stijl zelf uit de database,
         * en dat is precies de bedoeling -- maar hij hoort hem daarna terug
         * te zetten. Anders verandert een kijkje nemen stilletjes een
         * instelling.
         *
         * `null` betekent: er was nog geen rij. Dan heeft deze opdracht hem
         * gemaakt en hoort hij ook weer weg; een verse database blijft
         * daarmee vers.
         *
         * **De rij wordt achteraf opnieuw opgezocht en niet vastgehouden.**
         * `verstuurIn()` werkt met zijn eigen exemplaar uit `huidige()`, dus
         * een exemplaar dat hier blijft staan weet van niets -- dan zou
         * `delete()` op een rij slaan die niet bestaat en bleef de nieuwe
         * staan.
         */
        $origineel = SiteSetting::query()->first()?->mail_style;

        try {
            foreach ($stijlen as $stijl) {
                $this->verstuurIn($stijl, $adres);
            }
        } finally {
            $rij = SiteSetting::query()->first();

            if ($rij !== null) {
                if ($origineel === null) {
                    $rij->delete();
                } else {
                    $rij->mail_style = $origineel;
                    $rij->save();
                }
            }
        }

        $this->newLine();
        $this->components->info(
            'Verstuurd naar '.$adres.'. Bekijk ze op http://localhost:8025'
        );

        return self::SUCCESS;
    }

    /**
     * De stijlen die we gaan versturen.
     *
     * @return array<int, MailStijl>
     */
    private function gekozenStijlen(): array
    {
        $gevraagd = (string) ($this->option('stijl') ?? '');

        if ($gevraagd === '') {
            return MailStijl::cases();
        }

        $stijl = MailStijl::tryFrom($gevraagd);

        return $stijl === null ? [] : [$stijl];
    }

    /**
     * Alle drie de mails in één stijl.
     *
     * De stijl wordt in de database gezet en niet aan de mailable
     * meegegeven: zo loopt deze test door dezelfde weg als een echte mail,
     * inclusief de trait die de stijl ophaalt. Zou ik hem hier met de hand
     * op de mailable zetten, dan test ik mijn eigen opdracht en niet de
     * applicatie.
     *
     * **`sendNow()` en niet `send()`.** Die laatste zét een mailable die
     * `ShouldQueue` is alsnog in de wachtrij -- zie
     * `Mailer::sendMailable()`. Dan staat je testmail in de tabel `jobs` te
     * wachten op een worker, en zie je in MailHog niets gebeuren. Een
     * opdracht die bestaat om iets te laten zien hoort niet op een wachtrij
     * te wachten.
     */
    private function verstuurIn(MailStijl $stijl, string $adres): void
    {
        $instellingen = SiteSetting::huidige();
        $instellingen->mail_style = $stijl;
        $instellingen->save();

        $contact = ContactSetting::huidige();

        $this->components->task(
            'De bevestiging aan een bezoeker ('.$stijl->label().')',
            function () use ($adres, $contact, $stijl): bool {
                Mail::to($adres)
                    ->locale('nl')
                    ->sendNow(new ContactBevestigingMail(
                        naam: 'Jan de Vries',
                        onderwerp: '['.$stijl->label().'] '.$contact->bevestigingOnderwerp('nl'),
                        tekst: $contact->bevestigingTekst('nl'),
                        samenvatting: 'Vrijblijvend gesprek',
                    ));

                return true;
            },
        );

        $this->components->task(
            'De melding aan jou ('.$stijl->label().')',
            function () use ($adres, $stijl): bool {
                Mail::to($adres)->sendNow(new ContactMessageMail(
                    senderName: 'Jan de Vries',
                    senderEmail: 'jan@voorbeeld.test',
                    senderSubject: '['.$stijl->label().'] Vrijblijvend gesprek',
                    body: "Goedemiddag,\n\nWe zijn op zoek naar iemand die onze "
                        .'werkplekken en ons netwerk onder handen neemt. Een kantoor '
                        ."met twaalf mensen.\n\nKun je een indicatie geven van wat zo'n "
                        .'overstap kost?',
                    senderCompany: 'De Vries Interieurbouw',
                    senderPhone: '06 24 55 10 92',
                    senderLocale: 'nl',
                ));

                return true;
            },
        );

        /*
         * En de beveiligingsmelding, want dat is de enige mail met een
         * **knop** erin -- en een knop is precies wat je in beide stijlen
         * wil vergelijken: op licht is hij donkerblauw met witte tekst, op
         * de huisstijl cyaan met donkere tekst.
         */
        $this->components->task(
            'Een beveiligingsmelding, voor de knop ('.$stijl->label().')',
            function () use ($adres, $stijl): bool {
                Mail::to($adres)->sendNow(new SecurityAlertMail(
                    anomalies: [new Anomaly(
                        key: 'failed-logins',
                        title: '['.$stijl->label().'] Mislukte inlogpogingen',
                        count: 31,
                        threshold: 25,
                        details: [
                            'Een verzonnen melding, om de opmaak te bekijken.',
                            'Er is niets aan de hand.',
                        ],
                    )],
                    since: now()->subHour(),
                ));

                return true;
            },
        );
    }
}
