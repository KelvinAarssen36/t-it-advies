<?php

namespace App\Console\Commands;

use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\MailLog;
use Illuminate\Console\Command;

/**
 * Haalt de verzonnen data van `VoorbeeldDataSeeder` weer weg.
 *
 * **Dit bestaat omdat zaaien makkelijker is dan opruimen.** Negen aanvragen
 * met de hand verwijderen in het portaal vraagt negen keer een verse
 * 2FA-code, en dan nog de onderwerpen en het mailoverzicht. Zonder deze
 * opdracht blijft verzonnen data staan tot iemand de database weggooit --
 * en dat is precies hoe je op een dag echte aanvragen naast verzonnen
 * aanvragen hebt staan.
 *
 * **Hij gaat op het adres af en niet op een datum of een aantal.** Alles wat
 * de seeder maakt staat op `@voorbeeld.test`, een domein dat door de IANA is
 * gereserveerd en dus nooit van een echte bezoeker kan zijn. Een echte
 * aanvraag kan hier dus niet tussen vallen.
 *
 * De onderwerpen hebben geen adres, dus die gaan op hun naam. Dat is de
 * zwakste schakel van de twee: heeft de klant zelf een onderwerp "Offerte
 * aanvragen" gemaakt, dan verdwijnt dat mee. Daarom vraagt de opdracht om
 * bevestiging voordat hij iets doet, en staat erbij wat hij gaat weghalen.
 *
 * Draait alleen in local en testing, net als de seeder zelf.
 *
 * Zie database/seeders/VoorbeeldDataSeeder.php.
 */
class OpruimenVoorbeeldData extends Command
{
    protected $signature = 'voorbeeld:opruimen {--force : Zonder vragen}';

    protected $description = 'Haalt de verzonnen voorbeelddata weer uit de database';

    /** Hetzelfde domein als in de seeder. */
    private const DOMEIN = '@voorbeeld.test';

    /** De onderwerpen die de seeder neerzet, op naam. */
    private const ONDERWERPEN = [
        'Vrijblijvend gesprek',
        'Offerte aanvragen',
        'Storing of spoed',
        'Samenwerken',
        'Vacature of stage',
    ];

    public function handle(): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->components->error(
                'Deze opdracht draait alleen in local en testing.'
            );

            return self::FAILURE;
        }

        $aanvragen = ContactSubmission::query()
            ->where('email', 'like', '%'.self::DOMEIN)
            ->count();

        $mail = MailLog::query()
            ->where('message_id', 'like', 'voorbeeld-%')
            ->count();

        $onderwerpen = ContactSubject::query()
            ->whereIn('label_nl', self::ONDERWERPEN)
            ->count();

        if ($aanvragen + $mail + $onderwerpen === 0) {
            $this->components->info('Er staat geen voorbeelddata in de database.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Aanvragen', (string) $aanvragen);
        $this->components->twoColumnDetail('Regels in het mailoverzicht', (string) $mail);
        $this->components->twoColumnDetail('Onderwerpen', (string) $onderwerpen);

        /*
         * De onderwerpen gaan op naam en niet op adres, dus hier kan de
         * klant zijn eigen werk tussen zitten. Dat zeggen we met zoveel
         * woorden in plaats van het stil weg te halen.
         */
        if ($onderwerpen > 0) {
            $this->components->warn(
                'De onderwerpen worden op naam gevonden. Heb je er zelf een met '
                    .'dezelfde naam gemaakt, dan gaat die mee.'
            );
        }

        if (! $this->option('force') && ! $this->confirm('Weghalen?', true)) {
            $this->components->info('Niets gedaan.');

            return self::SUCCESS;
        }

        /*
         * De aanvragen eerst. Een onderwerp verwijderen zet `subject_id`
         * van zijn aanvragen op null (`nullOnDelete`), dus andersom zou een
         * aanvraag van de klant die naar een voorbeeldonderwerp verwijst
         * zijn onderwerptekst houden maar zijn verwijzing kwijtraken -- en
         * dan staat er "dit onderwerp bestaat niet meer" bij iets dat de
         * klant niet heeft gezien.
         */
        ContactSubmission::query()
            ->where('email', 'like', '%'.self::DOMEIN)
            ->delete();

        MailLog::query()
            ->where('message_id', 'like', 'voorbeeld-%')
            ->delete();

        ContactSubject::query()
            ->whereIn('label_nl', self::ONDERWERPEN)
            ->delete();

        $this->components->info('De voorbeelddata is weg.');

        return self::SUCCESS;
    }
}
