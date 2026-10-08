<?php

namespace App\Support\Backup;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;

/**
 * Wat er verandert als je deze back-up terugzet.
 *
 * **Een waarschuwing met cijfers erin leest iemand wél.** "Weet je het
 * zeker, alles wordt overschreven" is tekst die je wegklikt; "14 projecten
 * worden 11 projecten" is informatie waar je even van schrikt. Dat is het
 * hele doel van deze klasse.
 *
 * Hij rekent ook uit of de back-up nog bij het huidige schema past en of
 * hij wel van déze site komt. Allebei dingen die je vóór het terugzetten
 * wil weten en niet erna.
 *
 * Zie docs/operations/back-ups.md.
 */
class Verschilrapport
{
    public function __construct(private readonly Inhoudsregister $register) {}

    /**
     * @return array<string, mixed>
     */
    public function voor(Backup $backup): array
    {
        $regels = [];

        foreach ($this->register->bestaandeTabellen() as $tabel) {
            $nu = DB::table($tabel)->count();
            $straks = (int) ($backup->aantallen[$tabel] ?? 0);

            if ($nu === $straks && ! array_key_exists($tabel, $backup->aantallen)) {
                continue;
            }

            $regels[] = [
                'tabel' => $tabel,
                'label' => $this->label($tabel),
                'nu' => $nu,
                'straks' => $straks,
                'verschil' => $straks - $nu,
            ];
        }

        return [
            'regels' => $regels,
            'erbij' => array_sum(array_map(
                static fn (array $r): int => max(0, $r['verschil']),
                $regels,
            )),
            'eraf' => array_sum(array_map(
                static fn (array $r): int => max(0, -$r['verschil']),
                $regels,
            )),
            'anderSchema' => $this->anderSchema($backup),
            'andereSite' => $this->andereSite($backup),
            'bestaat' => $backup->bestaat(),
        ];
    }

    /**
     * Komt deze back-up van een ander schema dan wat er nu draait?
     *
     * Dat is geen beletsel -- een back-up van jaren terug mag je gewoon
     * terugzetten -- maar de eigenaar hoort het te weten, want er kunnen
     * velden ontbreken.
     */
    private function anderSchema(Backup $backup): bool
    {
        $nu = DB::table('migrations')->orderByDesc('id')->value('migration');

        return is_string($nu)
            && $backup->schema_merk !== null
            && $backup->schema_merk !== $nu;
    }

    /**
     * Komt deze back-up van een andere installatie?
     *
     * Bijna altijd een vergissing bij een upload, en zonder deze
     * controle onzichtbaar.
     */
    private function andereSite(Backup $backup): bool
    {
        if ($backup->site_merk === null) {
            return false;
        }

        $merk = hash('sha256', (string) config('app.key').'|'.(string) config('app.url'));

        return ! hash_equals($merk, $backup->site_merk);
    }

    /**
     * De naam van een tabel zoals de eigenaar hem kent.
     *
     * Hij ziet "Projecten" op zijn scherm staan en niet `projects`. Een
     * waarschuwing in tabelnamen is een waarschuwing voor ons, niet voor
     * hem.
     */
    private function label(string $tabel): string
    {
        return match ($tabel) {
            'site_settings' => __('Instellingen van je site'),
            'page_sections' => __('Indeling van je pagina'),
            'section_headings' => __('Koppen boven je onderdelen'),
            'experiences' => __('Loopbaan'),
            'experience_stats' => __('Cijfers bij je loopbaan'),
            'services' => __('Diensten'),
            'service_points' => __('Punten bij je diensten'),
            'certificates' => __('Certificaten'),
            'educations' => __('Opleidingen'),
            'statistics' => __('Statistieken'),
            'faq_items' => __('Veelgestelde vragen'),
            'about_settings' => __('Over mij'),
            'about_points' => __('Punten bij Over mij'),
            'projects' => __('Projecten'),
            'project_settings' => __('Instellingen van je projecten'),
            'work_steps' => __('Stappen van je werkwijze'),
            'contact_subjects' => __('Onderwerpen van je contactformulier'),
            'contact_fields' => __('Velden van je contactformulier'),
            'contact_settings' => __('Instellingen van je contactformulier'),
            default => $tabel,
        };
    }
}
