<?php

namespace App\Console\Commands;

use App\Models\AboutPoint;
use App\Models\AboutSetting;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\FaqItem;
use App\Models\MailLog;
use App\Models\Project;
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

    /**
     * De vragen uit de voorbeelddata, op hun Nederlandse tekst.
     *
     * Net als bij de onderwerpen: er is geen adres om op te herkennen, dus
     * dit gaat op tekst. Daarom waarschuwt het commando er ook voor.
     *
     * @var array<int, string>
     */
    private const VRAGEN = [
        'Wat kost een migratie?',
        'Hoe snel kun je beginnen?',
        'Werk je ook voor kleine bedrijven?',
        'Zit ik daarna aan je vast?',
        'Doe je ook beheer, of alleen advies?',
        'Wat gebeurt er als jij ziek bent?',
        'Kun je met mijn huidige leverancier samenwerken?',
        'Geef je ook trainingen?',
    ];

    /**
     * De punten uit de voorbeelddata, op hun Nederlandse tekst.
     *
     * @var array<int, string>
     */
    /**
     * De projecten van de voorbeelddata, op hun Nederlandse titel.
     *
     * Net als de onderwerpen en de vragen: er is geen e-mailadres om op
     * te gaan, dus het commando waarschuwt dat een eigen project met
     * dezelfde titel meegaat.
     */
    private const PROJECTEN = [
        'Migratie naar Exchange Online',
        'Onderzoek naar een eigen datacentrum',
        'Interim ICT-coördinator',
    ];

    private const PUNTEN = [
        'Geen afhankelijkheid van één leverancier',
        'Documentatie waarmee een ander het overneemt',
        'Werkt met wat er al staat',
        'Bereikbaar zonder tussenpersoon',
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

        $vragen = FaqItem::query()
            ->whereIn('question_nl', self::VRAGEN)
            ->count();

        /*
         * "Over mij" is één rij, en die is niet op tekst te herkennen --
         * de eigenaar heeft er maar één en die is óf van hem óf van de
         * voorbeelddata. Daarom gaat hij op de samenvatting die de seeder
         * neerzet; het commando waarschuwt daarvoor net als bij de
         * onderwerpen en de vragen.
         */
        $overMij = AboutSetting::query()
            ->where('summary_nl', 'like', 'Ik werk sinds 2008 in de IT%')
            ->count();

        $puntenAantal = AboutPoint::query()
            ->whereIn('text_nl', self::PUNTEN)
            ->count();

        $projecten = Project::query()
            ->whereIn('title_nl', self::PROJECTEN)
            ->count();

        if ($aanvragen + $mail + $onderwerpen + $vragen + $overMij + $puntenAantal + $projecten === 0) {
            $this->components->info('Er staat geen voorbeelddata in de database.');

            return self::SUCCESS;
        }

        $this->components->twoColumnDetail('Aanvragen', (string) $aanvragen);
        $this->components->twoColumnDetail('Regels in het mailoverzicht', (string) $mail);
        $this->components->twoColumnDetail('Onderwerpen', (string) $onderwerpen);
        $this->components->twoColumnDetail('Vragen', (string) $vragen);
        $this->components->twoColumnDetail('Over mij', (string) $overMij);
        $this->components->twoColumnDetail('Punten bij Over mij', (string) $puntenAantal);
        $this->components->twoColumnDetail('Projecten', (string) $projecten);

        /*
         * De onderwerpen gaan op naam en niet op adres, dus hier kan de
         * klant zijn eigen werk tussen zitten. Dat zeggen we met zoveel
         * woorden in plaats van het stil weg te halen.
         */
        if ($onderwerpen + $vragen + $overMij + $puntenAantal + $projecten > 0) {
            $this->components->warn(
                'De onderwerpen en de vragen worden op tekst gevonden. Heb je er '
                    .'zelf een met dezelfde tekst gemaakt, dan gaat die mee.'
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

        FaqItem::query()
            ->whereIn('question_nl', self::VRAGEN)
            ->delete();

        AboutPoint::query()
            ->whereIn('text_nl', self::PUNTEN)
            ->delete();

        Project::query()
            ->whereIn('title_nl', self::PROJECTEN)
            ->delete();

        AboutSetting::query()
            ->where('summary_nl', 'like', 'Ik werk sinds 2008 in de IT%')
            ->delete();

        $this->components->info('De voorbeelddata is weg.');

        return self::SUCCESS;
    }
}
