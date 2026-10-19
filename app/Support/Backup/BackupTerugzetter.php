<?php

namespace App\Support\Backup;

use App\Enums\PageSectionKey;
use App\Models\Backup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Zet een back-up terug.
 *
 * ## Bijwerken, niet wissen
 *
 * De voor de hand liggende aanpak -- elke tabel leegmaken en opnieuw
 * vullen -- heeft één echt probleem. `contact_submissions.subject_id`
 * wijst met `nullOnDelete` naar `contact_subjects`. Alle onderwerpen
 * weggooien zet dus bij **elke bestaande aanvraag** het onderwerp op
 * `null`, ook bij aanvragen die daarna gewoon blijven staan. De eigenaar
 * zet zijn projecten terug en is ongemerkt het onderwerp van al zijn
 * aanvragen kwijt.
 *
 * Daarom werkt het per tabel als een vergelijking, met behoud van de
 * oorspronkelijke `id`:
 *
 * - rij in allebei → bijwerken;
 * - rij alleen in de back-up → invoegen, met dezelfde `id`;
 * - rij alleen in de database → verwijderen.
 *
 * Een onderwerp dat in de back-up zat én nu bestaat wordt dus nooit
 * weggegooid, en de koppeling met oude aanvragen blijft heel.
 *
 * ## Eén transactie, en geen modelgebeurtenissen
 *
 * Alles draait in één transactie: gaat er iets mis, dan is er niets
 * veranderd. `Model::withoutEvents()` eromheen, want anders schrijft
 * `LogsActivity` honderden regels in het activiteitenlogboek voor wat de
 * eigenaar als één handeling heeft gedaan.
 *
 * ## De proef
 *
 * Met `proef: true` gebeurt precies hetzelfde, maar wordt de transactie
 * aan het eind teruggedraaid en worden er geen bestanden aangeraakt.
 * Dezelfde code, één pad -- zou de proef een eigen route hebben, dan
 * toets je iets anders dan je straks uitvoert.
 *
 * Zie docs/operations/back-ups.md.
 */
class BackupTerugzetter
{
    /**
     * Het voorvoegsel van een geparkeerde unieke waarde.
     *
     * Staat alleen binnen de transactie van een terugzetting in de
     * database, dus niemand ziet het ooit. Zie `parkeerUniekeWaarden()`.
     */
    private const PARKEER = '~herstel-';

    /**
     * Pad op de publieke schijf → de hash in de beeldmap.
     *
     * Komt uit het manifest; zonder deze kaart weet het terugzetten niet
     * welk bestand bij welke rij hoort.
     *
     * @var array<string, string>
     */
    private array $beeldkaart = [];

    public function __construct(private readonly Inhoudsregister $register) {}

    /**
     * @param  array<string, string>  $beelden  hash => pad, precies zoals in het manifest
     */
    public function metBeelden(array $beelden): self
    {
        $this->beeldkaart = array_flip($beelden);

        return $this;
    }

    /**
     * @param  array<string, array<int, array<string, mixed>>>  $data
     * @return array<int, string> wat er is overgeslagen, voor het verslag
     */
    public function zetTerug(array $data, bool $proef = false): array
    {
        $meldingen = [];

        $werk = function () use ($data, &$meldingen): void {
            Model::withoutEvents(function () use ($data, &$meldingen): void {
                $koppen = $this->bewaarKoppenVanNa(array_keys($data));

                foreach ($this->register->bestaandeTabellen() as $tabel) {
                    if (! array_key_exists($tabel, $data)) {
                        /*
                         * Een onderdeel dat er nu wel is maar niet in de
                         * back-up stond -- een module die later is
                         * gebouwd. Met rust laten: leegmaken zou
                         * gegevens weggooien die deze back-up nooit
                         * heeft gezien.
                         */
                        $meldingen[] = __('Het onderdeel :tabel bestond nog niet toen deze back-up werd gemaakt, en is ongemoeid gelaten.', [
                            'tabel' => $tabel,
                        ]);

                        continue;
                    }

                    foreach ($this->zetTabelTerug($tabel, $data[$tabel]) as $melding) {
                        $meldingen[] = $melding;
                    }
                }

                foreach ($this->zetKoppenTerug($koppen) as $melding) {
                    $meldingen[] = $melding;
                }
            });
        };

        foreach (array_keys($data) as $tabel) {
            if (! $this->register->kentTabel($tabel)) {
                $meldingen[] = __('Deze back-up bevat :tabel, en dat onderdeel bestaat niet meer. Dat is overgeslagen.', [
                    'tabel' => $tabel,
                ]);
            }
        }

        if ($proef) {
            /*
             * Doen alsof, en daarna alles terugdraaien. De uitzondering
             * is het signaal om terug te draaien en geen storing; hij
             * wordt hier dus opgevangen en niet doorgegeven.
             */
            try {
                DB::transaction(function () use ($werk): void {
                    $werk();

                    throw new ProefAfgerond;
                });
            } catch (ProefAfgerond) {
                // Zo hoort het te gaan.
            }

            return $meldingen;
        }

        DB::transaction($werk);

        // Pas hierna de bestanden: die kennen geen rollback. Zou dit
        // ertussen zitten, dan had een mislukte terugzetting de beelden
        // al overschreven.
        $this->zetBeeldenTerug($data);

        return $meldingen;
    }

    /**
     * Eén tabel gelijktrekken met wat er in de back-up staat.
     *
     * @param  array<int, array<string, mixed>>  $rijen
     * @return array<int, string>
     */
    private function zetTabelTerug(string $tabel, array $rijen): array
    {
        $meldingen = [];
        $kolommen = $this->register->kolommenVan($tabel);

        $uitDeBackup = [];

        foreach ($rijen as $rij) {
            if (isset($rij['id'])) {
                $uitDeBackup[] = (int) $rij['id'];
            }
        }

        $nuAanwezig = DB::table($tabel)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        // Wat er na deze back-up bij is gekomen, gaat weg.
        $teveel = array_values(array_diff($nuAanwezig, $uitDeBackup));

        if ($teveel !== []) {
            DB::table($tabel)->whereIn('id', $teveel)->delete();
        }

        /*
         * Eerst de unieke waarden uit de weg, dan pas bijwerken.
         *
         * **Dit is de reparatie van een echte storing.** Bijwerken gaat
         * rij voor rij op `id`, en dat gaat mis zodra twee rijen van
         * waarde wisselen: zet je rij 2 op `diensten` terwijl rij 5 die
         * sleutel nog heeft, dan weigert de database met
         * "Duplicate entry 'diensten'". De terugzetting breekt dan
         * halverwege af -- gelukkig binnen een transactie, dus er bleef
         * niets half gedaan achter, maar de eigenaar zag een crash.
         *
         * Dat gebeurt niet bij een back-up van vanmiddag, wel zodra de
         * nummers verschoven zijn: na een nieuwe installatie, na opnieuw
         * zaaien, of bij een bestand van een andere omgeving. Precies de
         * gevallen waarvoor terugzetten bedoeld is.
         *
         * We parkeren daarom elke unieke waarde die nog op een rij staat
         * op een tijdelijke eigen waarde. Daarna is het veld vrij en kan
         * elke rij zijn eigen waarde terugkrijgen, in welke volgorde dan
         * ook. Alles staat in dezelfde transactie, dus de geparkeerde
         * waarden zijn nooit van buiten te zien.
         */
        $this->parkeerUniekeWaarden($tabel);

        $onbekend = [];

        foreach ($rijen as $rij) {
            $schoon = [];

            foreach ($rij as $kolom => $waarde) {
                if (in_array($kolom, $kolommen, true)) {
                    $schoon[$kolom] = $waarde;

                    continue;
                }

                $onbekend[$kolom] = true;
            }

            if (! isset($schoon['id'])) {
                continue;
            }

            DB::table($tabel)->updateOrInsert(['id' => $schoon['id']], $schoon);
        }

        if ($onbekend !== []) {
            $meldingen[] = __('In :tabel staan velden die niet meer bestaan (:velden). Die zijn overgeslagen.', [
                'tabel' => $tabel,
                'velden' => implode(', ', array_keys($onbekend)),
            ]);
        }

        if ($rijen !== []) {
            $erbij = array_values(array_diff($kolommen, array_keys($rijen[0])));

            if ($erbij !== []) {
                $meldingen[] = __('In :tabel zijn er sinds deze back-up velden bijgekomen (:velden). Die houden hun standaardwaarde.', [
                    'tabel' => $tabel,
                    'velden' => implode(', ', $erbij),
                ]);
            }
        }

        return $meldingen;
    }

    /**
     * Elke unieke waarde in deze tabel tijdelijk op een eigen waarde zetten.
     *
     * Zie de uitleg in `zetTabelTerug()`. De waarde bevat het `id`, dus hij
     * is per rij uniek, en het voorvoegsel is iets wat een eigenaar nooit
     * intikt.
     *
     * **De unieke kolommen komen uit het schema zelf** en niet uit een
     * lijst hier. Zo'n lijst zou verouderen zodra er een module bijkomt,
     * en dan is deze storing terug zonder dat iets het meldt.
     */
    private function parkeerUniekeWaarden(string $tabel): void
    {
        $kolommen = $this->uniekeTekstkolommen($tabel);

        if ($kolommen === []) {
            return;
        }

        foreach (DB::table($tabel)->pluck('id') as $id) {
            DB::table($tabel)->where('id', $id)->update(
                array_fill_keys($kolommen, self::PARKEER.$id),
            );
        }
    }

    /**
     * De kolommen met een eigen unieke index waar tekst in staat.
     *
     * Alleen tekst: een geparkeerde waarde moet in de kolom passen, en
     * een getal of een datum kun je niet op `~herstel-3` zetten. In de
     * tabellen die meegaan zijn ze allemaal tekst -- `page_sections.key`,
     * `section_headings.section`, `projects.slug` en `contact_fields.key`
     * -- maar die controle staat er voor de volgende module.
     *
     * @return array<int, string>
     */
    private function uniekeTekstkolommen(string $tabel): array
    {
        $uit = [];

        foreach (Schema::getIndexes($tabel) as $index) {
            if (($index['primary'] ?? false) || ! ($index['unique'] ?? false)) {
                continue;
            }

            foreach ((array) ($index['columns'] ?? []) as $kolom) {
                if ($kolom === 'id') {
                    continue;
                }

                if (! in_array(
                    Schema::getColumnType($tabel, $kolom),
                    ['char', 'varchar', 'string', 'text'],
                    true,
                )) {
                    continue;
                }

                $uit[$kolom] = true;
            }
        }

        return array_keys($uit);
    }

    /**
     * Koppen opzijzetten die deze back-up niet kan kennen.
     *
     * Een back-up van vóór het LinkedIn-beheer heeft geen kop voor dat
     * blok; zijn tekst stond toen in de code. De gewone vergelijking in
     * `zetTabelTerug()` zou de kop die de eigenaar sindsdien schreef dan
     * als "erbij gekomen" verwijderen. Dat is precies wat er met een hele
     * tabel van na de back-up níet gebeurt, dus hier ook niet: de rij gaat
     * opzij en komt na het terugzetten weer terug. Zie
     * `Inhoudsregister::kopVanVoorDeBackup()`.
     *
     * @param  array<int, string>  $tabellenInDeBackup
     * @return array<int, array<string, mixed>>
     */
    private function bewaarKoppenVanNa(array $tabellenInDeBackup): array
    {
        if (! in_array('section_headings', $this->register->bestaandeTabellen(), true)) {
            return [];
        }

        $secties = $this->register->kopVanVoorDeBackup($tabellenInDeBackup);

        if ($secties === []) {
            return [];
        }

        return DB::table('section_headings')
            ->whereIn('section', $secties)
            ->get()
            ->map(static fn (object $rij): array => (array) $rij)
            ->all();
    }

    /**
     * De opzijgezette koppen terugzetten.
     *
     * **Zonder hun oude `id`.** Die kan inmiddels door een rij uit de
     * back-up bezet zijn; de sleutel die telt is `section`, en die is
     * uniek. Staat er na het terugzetten al een kop voor dat onderdeel --
     * de back-up had er toch een -- dan wint de back-up.
     *
     * @param  array<int, array<string, mixed>>  $koppen
     * @return array<int, string>
     */
    private function zetKoppenTerug(array $koppen): array
    {
        $meldingen = [];

        foreach ($koppen as $kop) {
            $bestaat = DB::table('section_headings')
                ->where('section', $kop['section'])
                ->exists();

            if ($bestaat) {
                continue;
            }

            unset($kop['id']);

            DB::table('section_headings')->insert($kop);

            $meldingen[] = __('De kop van :onderdeel stond nog niet in deze back-up, en is gebleven zoals hij was.', [
                'onderdeel' => PageSectionKey::tryFrom((string) $kop['section'])?->label() ?? (string) $kop['section'],
            ]);
        }

        return $meldingen;
    }

    /**
     * De beelden terug op hun plek zetten.
     *
     * Ze staan in de gedeelde map onder hun hash; de rij zegt onder welk
     * pad ze op de publieke schijf horen. Een beeld dat er al staat laten
     * we met rust -- dan is het hetzelfde bestand, want de naam ís de
     * inhoud.
     *
     * @param  array<string, array<int, array<string, mixed>>>  $data
     */
    private function zetBeeldenTerug(array $data): void
    {
        if ($this->beeldkaart === []) {
            return;
        }

        $schijf = Storage::disk('local');
        $publiek = Storage::disk('public');

        foreach ($this->register->alleBeeldkolommen() as $tabel => $kolommen) {
            foreach ($data[$tabel] ?? [] as $rij) {
                foreach ($kolommen as $kolom) {
                    $pad = $rij[$kolom] ?? null;

                    if (! is_string($pad) || $pad === '' || $publiek->exists($pad)) {
                        continue;
                    }

                    $hash = $this->beeldkaart[$pad] ?? null;

                    if ($hash === null) {
                        continue;
                    }

                    $bron = Backup::BEELDMAP.'/'.$hash.'.webp';
                    $inhoud = $schijf->exists($bron) ? $schijf->get($bron) : null;

                    if (is_string($inhoud)) {
                        $publiek->put($pad, $inhoud);
                    }
                }
            }
        }
    }
}
