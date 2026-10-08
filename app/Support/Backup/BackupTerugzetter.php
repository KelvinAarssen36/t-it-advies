<?php

namespace App\Support\Backup;

use App\Models\Backup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
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
