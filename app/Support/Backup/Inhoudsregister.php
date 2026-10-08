<?php

namespace App\Support\Backup;

use Illuminate\Support\Facades\Schema;

/**
 * Wat er in een back-up hoort, en wat er nooit in mag.
 *
 * **Dit bestand is het hart van de hele voorziening.** Het maken, het
 * terugzetten en het controleren van een geüpload bestand lezen alle drie
 * hieruit. Komt er een module bij, dan is dat hier één regel -- en niet
 * drie plekken die stilletjes uiteen gaan lopen.
 *
 * ## De scheiding
 *
 * Er is verschil tussen **wat de eigenaar heeft gemaakt** en **wat er is
 * gebeurd**. Alleen het eerste gaat mee.
 *
 * Buiten de back-up blijven met opzet:
 *
 * - **`users`, `passkeys`, de 2FA-kolommen, `email_changes`** -- zijn
 *   account. Zou een terugzetting die aanraken, dan logt de eigenaar
 *   zichzelf uit met een oud wachtwoord of een verdwenen passkey. Dit
 *   portaal heeft één account; dat is onherstelbaar.
 * - **`contact_submissions`** -- berichten van bezoekers. Die hebben een
 *   bewaartermijn van een jaar en kunnen op verzoek gewist zijn. In een
 *   back-up zetten maakt een kopie zonder klok, en terugzetten zou gewiste
 *   berichten weer tot leven wekken. Zie
 *   docs/security/verzoeken-van-bezoekers.md.
 * - **`security_events`, `activity_entries`, `mail_logs`, de
 *   bezoekcijfers** -- dat is wat er gebeurd is. Dat zet je niet terug;
 *   dan herschrijf je de geschiedenis.
 * - **`roles` en `permissions`** -- die komen uit een seeder en horen bij
 *   de code.
 *
 * Zie docs/operations/back-ups.md.
 */
class Inhoudsregister
{
    /**
     * De tabellen, in de volgorde waarin ze teruggezet moeten worden.
     *
     * **De volgorde is geen smaak.** `service_points` heeft een
     * verwijzing naar `services` met `cascadeOnDelete`; staat die eerder,
     * dan verdwijnen de punten zodra hun dienst wordt aangeraakt.
     *
     * @var array<int, string>
     */
    private const TABELLEN = [
        'site_settings',
        'page_sections',
        'section_headings',

        'experiences',
        'experience_stats',

        'services',
        'service_points',

        'certificates',
        'educations',
        'statistics',
        'faq_items',

        'about_settings',
        'about_points',

        'projects',
        'project_settings',

        'work_steps',

        'contact_subjects',
        'contact_fields',
        'contact_settings',
    ];

    /**
     * Kolommen waar een pad naar een bestand in staat.
     *
     * Wat hier staat wordt bij het maken meegenomen als echt bestand, en
     * bij het terugzetten weer op zijn plek gezet. Staat een kolom hier
     * niet in, dan gaat alleen de tekst mee en is het beeld na een
     * terugzetting weg -- precies het soort stille fout waar dit register
     * voor bestaat.
     *
     * @var array<string, array<int, string>>
     */
    private const BEELDKOLOMMEN = [
        'experiences' => ['logo_path'],
        'certificates' => ['logo_path'],
        'projects' => ['image_path'],
        'about_settings' => ['photo_path'],
    ];

    /**
     * Tabellen die nooit in een back-up mogen staan.
     *
     * Deze lijst is er niet om gelezen te worden door de code die de
     * back-up maakt -- die kijkt naar TABELLEN. Hij staat hier zodat
     * `BackupGeheimenTest` hem kan gebruiken, en zodat iemand die een
     * tabel toevoegt gedwongen wordt na te denken over welke van de twee
     * lijsten het wordt.
     *
     * @var array<int, string>
     */
    public const VERBODEN = [
        'users',
        'passkeys',
        'password_reset_tokens',
        'sessions',
        'email_changes',

        /*
         * De rollen en rechten. Die komen uit RolesAndPermissionsSeeder
         * en horen bij de code, niet bij de inhoud: dit portaal heeft één
         * recht en één rol, en die veranderen alleen als wij iets
         * bouwen. Zouden ze meegaan, dan kan een oude back-up het recht
         * terugzetten zoals het toen was -- en dat is precies hoe je
         * jezelf uit je eigen beheerscherm werkt.
         */
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'contact_submissions',
        'security_events',
        'activity_entries',
        'mail_logs',
        'site_day_dimensions',
        'site_day_totals',
        'site_visitor_codes',
        'site_visitor_salts',
        'jobs',
        'job_batches',
        'failed_jobs',
        'cache',
        'cache_locks',
        'backups',
    ];

    /**
     * Alle tabellen in de juiste volgorde.
     *
     * @return array<int, string>
     */
    public function tabellen(): array
    {
        return self::TABELLEN;
    }

    /**
     * Alleen de tabellen die in deze database ook echt bestaan.
     *
     * Nodig voor het geval dat jij beschreef: een back-up van jaren terug
     * terugzetten in een schema dat intussen is veranderd. Dan kan er een
     * tabel in de lijst staan die hier niet meer is.
     *
     * @return array<int, string>
     */
    public function bestaandeTabellen(): array
    {
        return array_values(array_filter(
            self::TABELLEN,
            static fn (string $tabel): bool => Schema::hasTable($tabel),
        ));
    }

    public function kentTabel(string $tabel): bool
    {
        return in_array($tabel, self::TABELLEN, true);
    }

    /**
     * De kolommen van een tabel waar een bestandspad in staat.
     *
     * @return array<int, string>
     */
    public function beeldkolommen(string $tabel): array
    {
        return self::BEELDKOLOMMEN[$tabel] ?? [];
    }

    /**
     * Elke tabel die beelden heeft, met zijn kolommen.
     *
     * @return array<string, array<int, string>>
     */
    public function alleBeeldkolommen(): array
    {
        return self::BEELDKOLOMMEN;
    }

    /**
     * De kolommen die deze database voor een tabel kent.
     *
     * Bij het terugzetten is dit de zeef: een kolom die in de back-up
     * staat maar hier niet meer bestaat wordt overgeslagen, en andersom
     * vult de database zijn eigen standaard in. Allebei worden ze gemeld;
     * zie Verschilrapport.
     *
     * @return array<int, string>
     */
    public function kolommenVan(string $tabel): array
    {
        return Schema::getColumnListing($tabel);
    }
}
