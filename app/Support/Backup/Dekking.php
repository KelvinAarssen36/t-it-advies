<?php

namespace App\Support\Backup;

use App\Models\Backup;
use App\Support\Datum;
use Illuminate\Support\Collection;

/**
 * Wat er in een back-up meegaat, in de woorden van de eigenaar.
 *
 * **Dit bestaat om gerust te stellen en niet om te controleren.** Het
 * register ernaast weet precies welke tabellen er meegaan; dat is een
 * lijst met namen als `about_points` waar de klant niets aan heeft. Hier
 * staat dezelfde waarheid in onderdelen die hij herkent, met achter elk
 * de datum waarop het voor het laatst is vastgelegd.
 *
 * **De dekking wordt afgeleid, niet opgeschreven.** Elke tabel uit
 * `Inhoudsregister::TABELLEN` hoort bij precies één onderdeel hieronder.
 * Staat er een tabel bij die nergens in past, dan komt die er met zijn
 * eigen naam onder te staan in plaats van stil weg te vallen -- liever
 * databasejargon op het scherm dan een lijst die onvolledig is terwijl hij
 * compleet oogt. Dat is dezelfde afweging als bij het verschilrapport.
 *
 * **De datum komt uit `aantallen` op de back-uprij en niet uit het
 * bestand.** Elke back-up schrijft daar op hoeveel rijen hij per tabel
 * heeft meegenomen, dus staat de naam van een tabel erin, dan zat dat
 * onderdeel erin -- ook met nul rijen. Zo hoeft er geen zip opengemaakt te
 * worden om deze lijst te tonen.
 *
 * Dat verschil is zichtbaar bij een nieuw onderdeel: een back-up van vóór
 * het bestond heeft die tabel niet, dus staat er eerlijk "nog niet
 * meegenomen" tot de eigenaar een nieuwe maakt.
 *
 * Zie docs/operations/back-ups.md.
 */
class Dekking
{
    public function __construct(private readonly Inhoudsregister $register) {}

    /**
     * De onderdelen zoals de eigenaar ze kent, met de tabellen eronder.
     *
     * De volgorde is die van het beheerscherm en niet die van de database:
     * dit is een lijst om gerust van te worden, dus hij leest van "mijn
     * site" naar de losse onderdelen.
     *
     * @var array<string, array<int, string>>
     */
    private const ONDERDELEN = [
        'Je site en de indeling' => [
            'site_settings',
            'page_sections',
            'section_headings',
        ],
        'Over mij' => ['about_settings', 'about_points'],
        'Diensten' => ['services', 'service_points'],
        'Werkwijze' => ['work_steps'],
        'Projecten' => ['projects', 'project_settings'],
        'Loopbaan' => ['experiences', 'experience_stats'],
        'Certificaten' => ['certificates'],
        'Opleidingen' => ['educations'],
        'Statistieken' => ['statistics'],
        'Kerngegevens' => ['core_facts'],
        'Veelgestelde vragen' => ['faq_items'],
        'Contactformulier' => [
            'contact_subjects',
            'contact_fields',
            'contact_settings',
        ],
        'LinkedIn-blok' => ['linkedin_settings'],
        'Voettekst' => ['footer_settings'],
    ];

    /**
     * De lijst voor het scherm.
     *
     * @return array<int, array{naam: string, laatst: ?string}>
     */
    public function voorHetScherm(): array
    {
        $backups = Backup::query()
            ->orderByDesc('vastgelegd_op')
            ->get(['vastgelegd_op', 'aantallen']);

        $rijen = [];

        foreach ($this->onderdelen() as $naam => $tabellen) {
            $rijen[] = [
                'naam' => __($naam),
                'laatst' => $this->laatstMet($backups, $tabellen),
            ];
        }

        /*
         * De beelden staan niet in een tabel maar gaan wel mee: ze zitten
         * als echt bestand in de zip. Zonder deze regel zou iemand die de
         * lijst leest kunnen denken dat alleen zijn teksten veilig zijn, en
         * dat is precies de onzekerheid die dit venster moet wegnemen.
         *
         * Hun datum is die van de nieuwste back-up: elke back-up neemt de
         * beelden mee die er op dat moment staan.
         */
        $rijen[] = [
            'naam' => __('Je foto-s, logo-s en afbeeldingen'),
            'laatst' => Datum::dag($backups->first()?->vastgelegd_op),
        ];

        return $rijen;
    }

    /**
     * De onderdelen, met achteraan wat nergens in paste.
     *
     * @return array<string, array<int, string>>
     */
    private function onderdelen(): array
    {
        $onderdelen = self::ONDERDELEN;

        $ingedeeld = array_merge(...array_values($onderdelen));

        foreach ($this->register->tabellen() as $tabel) {
            if (! in_array($tabel, $ingedeeld, true)) {
                $onderdelen[$tabel] = [$tabel];
            }
        }

        return $onderdelen;
    }

    /**
     * De dag van de nieuwste back-up die dit onderdeel bevatte.
     *
     * @param  Collection<int, Backup>  $backups
     * @param  array<int, string>  $tabellen
     */
    private function laatstMet(Collection $backups, array $tabellen): ?string
    {
        foreach ($backups as $backup) {
            $aantallen = (array) $backup->aantallen;

            foreach ($tabellen as $tabel) {
                if (array_key_exists($tabel, $aantallen)) {
                    return Datum::dag($backup->vastgelegd_op);
                }
            }
        }

        return null;
    }
}
