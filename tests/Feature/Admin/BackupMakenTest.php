<?php

namespace Tests\Feature\Admin;

use App\Models\Backup;
use App\Models\ContactSubmission;
use App\Models\Project;
use App\Models\User;
use App\Support\Backup\BackupLezer;
use App\Support\Backup\BackupMaker;
use App\Support\Backup\Inhoudsregister;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Een back-up maken, en weten dat hij deugt.
 *
 * Drie vragen, en de derde is de belangrijkste:
 *
 * 1. zit alles erin wat erin hoort?
 * 2. zit er niets in wat er níet in hoort?
 * 3. **werkt hij ook echt?**
 *
 * Die derde is de nul uit de 3-2-1-1-0-regel. Een back-up die nooit is
 * teruggelezen is een aanname, en een aanname waar je op rekent als het
 * misgaat is erger dan niets.
 *
 * Zie docs/operations/back-ups.md.
 */
class BackupMakenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');
    }

    private function maker(): BackupMaker
    {
        return app(BackupMaker::class);
    }

    /* --- Wat erin zit ----------------------------------------------------- */

    public function test_every_table_from_the_register_is_in_it(): void
    {
        $backup = $this->maker()->maak();

        $register = app(Inhoudsregister::class);

        foreach ($register->bestaandeTabellen() as $tabel) {
            $this->assertArrayHasKey(
                $tabel,
                $backup->aantallen,
                "Het onderdeel {$tabel} staat niet in de back-up.",
            );
        }
    }

    public function test_the_rows_really_travel_along(): void
    {
        Project::factory()->count(3)->create();

        $backup = $this->maker()->maak();

        $this->assertSame(3, $backup->aantallen['projects']);
    }

    /**
     * De beelden gaan mee.
     *
     * Zonder deze test is de back-up een lijst teksten die na het
     * terugzetten naar kapotte plaatjes wijst -- en dat merk je pas als
     * je hem nodig hebt.
     */
    public function test_the_images_travel_along(): void
    {
        $pad = 'projecten/voorbeeld.webp';
        Storage::disk('public')->put($pad, $this->eenAfbeelding());

        Project::factory()->create(['image_path' => $pad]);

        $backup = $this->maker()->maak();

        $zip = new ZipArchive;
        $zip->open((string) $backup->pad());

        $hash = hash('sha256', (string) Storage::disk('public')->get($pad));

        $this->assertNotFalse(
            $zip->locateName('media/'.$hash.'.webp'),
            'Het beeld van het project zit niet in de back-up.',
        );

        $zip->close();

        // En in de gedeelde map, zodat een tweede back-up hem niet
        // opnieuw hoeft op te slaan.
        Storage::disk('local')->assertExists(Backup::BEELDMAP.'/'.$hash.'.webp');
    }

    public function test_an_image_is_stored_only_once_across_backups(): void
    {
        $pad = 'projecten/voorbeeld.webp';
        Storage::disk('public')->put($pad, $this->eenAfbeelding());
        Project::factory()->create(['image_path' => $pad]);

        $this->maker()->maak();
        $this->travel(1)->minute();
        $this->maker()->maak();

        $this->assertCount(
            1,
            Storage::disk('local')->files(Backup::BEELDMAP),
            'Hetzelfde beeld staat twee keer in de gedeelde map.',
        );
    }

    /* --- Wat er niet in zit ------------------------------------------------ */

    /**
     * Er staat geen enkel geheim en geen bezoekersgegeven in het bestand.
     *
     * Deze test leest de zip als tekst en zoekt op kolomnamen. Dat is met
     * opzet grof: hij blijft daardoor gelden als er later een tabel bij
     * komt die iemand per ongeluk in het register zet.
     */
    public function test_no_secrets_and_no_visitor_data_end_up_in_the_file(): void
    {
        User::factory()->create();
        ContactSubmission::factory()->create([
            'email' => 'bezoeker@voorbeeld.test',
            'message' => 'Een bericht dat nergens anders hoort te staan.',
        ]);

        $backup = $this->maker()->maak();

        $inhoud = (string) file_get_contents((string) $backup->pad());

        foreach ([
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'bezoeker@voorbeeld.test',
            'Een bericht dat nergens anders hoort te staan.',
        ] as $naald) {
            $this->assertStringNotContainsString(
                $naald,
                $inhoud,
                "\"{$naald}\" staat in het back-upbestand en hoort daar niet.",
            );
        }
    }

    /**
     * Elke tabel in de database is ingedeeld.
     *
     * **Dit is de test die voorkomt dat er ooit iets wordt vergeten.** Een
     * nieuwe module levert een nieuwe tabel op, en die hoort óf mee te
     * gaan in een back-up óf bewust buiten te blijven. Wat in geen van
     * beide lijsten staat is geen keuze maar een gat -- en dat merk je
     * pas als de eigenaar iets terugzet en een deel van zijn website weg
     * is.
     *
     * Komt deze test om, voeg de tabel dan toe aan `TABELLEN` (hij is van
     * de eigenaar) of aan `VERBODEN` (hij is van het portaal).
     */
    public function test_every_table_in_the_database_is_classified(): void
    {
        $register = app(Inhoudsregister::class);

        $vergeten = [];

        foreach (Schema::getTableListing(schema: null, schemaQualified: false) as $tabel) {
            if ($tabel === 'migrations') {
                continue;
            }

            if ($register->kentTabel($tabel)) {
                continue;
            }

            if (in_array($tabel, Inhoudsregister::VERBODEN, true)) {
                continue;
            }

            $vergeten[] = $tabel;
        }

        $this->assertSame(
            [],
            $vergeten,
            'Deze tabellen staan niet in het register en niet op de verboden lijst, '
                .'dus er is geen keuze over gemaakt: '.implode(', ', $vergeten),
        );
    }

    public function test_the_forbidden_tables_are_not_in_the_register(): void
    {
        $register = app(Inhoudsregister::class);

        foreach (Inhoudsregister::VERBODEN as $tabel) {
            $this->assertFalse(
                $register->kentTabel($tabel),
                "{$tabel} staat in het register en hoort er niet in.",
            );
        }
    }

    /* --- Of hij werkt ------------------------------------------------------ */

    public function test_a_fresh_backup_passes_the_check(): void
    {
        $backup = $this->maker()->maak();

        // `maak()` controleert zelf al; dit legt vast dat dat ook wordt
        // vastgelegd op de rij.
        $this->assertNotNull($backup->gecontroleerd_op);

        $uitkomst = app(BackupLezer::class)->controleer($backup);

        $this->assertTrue($uitkomst->geslaagd, (string) $uitkomst->melding);
    }

    /**
     * De proefterugzetting laat de database met rust.
     *
     * Dit is de hele reden dat de knop *Controleren* bestaat en veilig
     * is: hij zet echt terug, en draait het daarna terug.
     */
    public function test_the_trial_restore_changes_nothing(): void
    {
        Project::factory()->count(2)->create();

        $backup = $this->maker()->maak();

        Project::factory()->count(3)->create();
        $this->assertSame(5, Project::query()->count());

        $uitkomst = app(BackupLezer::class)->controleer($backup, metProef: true);

        $this->assertTrue($uitkomst->geslaagd, (string) $uitkomst->melding);
        $this->assertTrue($uitkomst->proefGedraaid);

        $this->assertSame(
            5,
            Project::query()->count(),
            'De proef heeft de database veranderd, en dat mag niet.',
        );
    }

    public function test_a_damaged_file_is_refused(): void
    {
        $backup = $this->maker()->maak();

        // Eén byte in de gegevens veranderen is genoeg.
        $pad = (string) $backup->pad();
        $zip = new ZipArchive;
        $zip->open($pad);
        $data = (string) $zip->getFromName('data.json');
        $zip->addFromString('data.json', $data.' ');
        $zip->close();

        $uitkomst = app(BackupLezer::class)->controleer($backup);

        $this->assertFalse($uitkomst->geslaagd);
        $this->assertStringContainsString('beschadigd', (string) $uitkomst->melding);
    }

    public function test_a_file_with_a_damaged_image_is_refused(): void
    {
        $pad = 'projecten/voorbeeld.webp';
        Storage::disk('public')->put($pad, $this->eenAfbeelding());
        Project::factory()->create(['image_path' => $pad]);

        $backup = $this->maker()->maak();

        $hash = hash('sha256', (string) Storage::disk('public')->get($pad));

        $zip = new ZipArchive;
        $zip->open((string) $backup->pad());
        $zip->deleteName('media/'.$hash.'.webp');
        $zip->close();

        $uitkomst = app(BackupLezer::class)->controleer($backup);

        $this->assertFalse($uitkomst->geslaagd);
    }

    public function test_the_manifest_records_the_schema(): void
    {
        $backup = $this->maker()->maak();

        $this->assertNotNull($backup->schema_merk);
        $this->assertNotNull($backup->site_merk);
    }

    /**
     * Een echt, klein beeldbestand.
     *
     * Het tijdelijke bestand van `UploadedFile::fake()` wordt opgeruimd
     * zodra het object wordt vrijgegeven, dus hij blijft hier in een
     * variabele staan tot de inhoud is gelezen.
     */
    private function eenAfbeelding(): string
    {
        $bestand = UploadedFile::fake()->image('beeld.webp', 64, 64);

        return (string) $bestand->getContent();
    }
}
