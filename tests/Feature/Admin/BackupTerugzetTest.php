<?php

namespace Tests\Feature\Admin;

use App\Models\Backup;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\Project;
use App\Models\User;
use App\Support\Backup\BackupLezer;
use App\Support\Backup\BackupMaker;
use App\Support\Backup\BackupTerugzetter;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Terugzetten: de ingrijpendste knop van het portaal.
 *
 * Wat hier moet kloppen, in volgorde van hoe erg het is als het niet
 * klopt:
 *
 * 1. **het account blijft ongemoeid** -- anders sluit de eigenaar
 *    zichzelf buiten en is er niemand meer die kan inloggen;
 * 2. **de berichten van bezoekers blijven staan**, mét hun onderwerp;
 * 3. er ligt een veiligheidskopie, zodat ook dit ongedaan te maken is;
 * 4. en pas daarna: de inhoud gaat terug zoals het hoort.
 *
 * Zie docs/operations/back-ups.md.
 */
class BackupTerugzetTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = '';

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        Storage::fake('public');

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function eigenaar(): User
    {
        $this->secret = (new Google2FA)->generateSecretKey();

        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($this->secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    private function code(): string
    {
        return (new Google2FA)->getCurrentOtp($this->secret);
    }

    private function maak(): Backup
    {
        return app(BackupMaker::class)->maak();
    }

    /** Terugzetten buiten de controller om, voor de tests die niet over de sloten gaan. */
    private function zetTerug(Backup $backup): void
    {
        $lezer = app(BackupLezer::class);
        $pad = (string) $backup->pad();

        $manifest = $lezer->manifest($pad);
        $data = $lezer->data($pad, (string) $manifest['checksum']);

        app(BackupTerugzetter::class)
            ->metBeelden((array) ($manifest['beelden'] ?? []))
            ->zetTerug($data);
    }

    /* --- De inhoud --------------------------------------------------------- */

    public function test_a_row_added_after_the_backup_disappears(): void
    {
        Project::factory()->count(2)->create();

        $backup = $this->maak();

        Project::factory()->create(['title_nl' => 'Later toegevoegd']);
        $this->assertSame(3, Project::query()->count());

        $this->zetTerug($backup);

        $this->assertSame(2, Project::query()->count());
        $this->assertNull(Project::query()->where('title_nl', 'Later toegevoegd')->first());
    }

    public function test_a_changed_row_goes_back(): void
    {
        $project = Project::factory()->create(['title_nl' => 'Zoals het was']);

        $backup = $this->maak();

        $project->forceFill(['title_nl' => 'Verprutst'])->save();

        $this->zetTerug($backup);

        $this->assertSame('Zoals het was', $project->refresh()->title_nl);
    }

    public function test_a_deleted_row_comes_back_with_its_own_id(): void
    {
        $project = Project::factory()->create(['title_nl' => 'Per ongeluk weg']);
        $id = $project->id;

        $backup = $this->maak();

        $project->delete();

        $this->zetTerug($backup);

        $terug = Project::query()->find($id);

        $this->assertNotNull($terug);
        $this->assertSame('Per ongeluk weg', $terug->title_nl);
    }

    public function test_an_image_comes_back_too(): void
    {
        $pad = 'projecten/voorbeeld.webp';
        $bestand = UploadedFile::fake()->image('beeld.webp', 64, 64);
        Storage::disk('public')->put($pad, (string) $bestand->getContent());

        Project::factory()->create(['image_path' => $pad]);

        $backup = $this->maak();

        Storage::disk('public')->delete($pad);
        Storage::disk('public')->assertMissing($pad);

        $this->zetTerug($backup);

        Storage::disk('public')->assertExists($pad);
    }

    /* --- Wat níet mag veranderen -------------------------------------------- */

    /**
     * Het account blijft precies zoals het was.
     *
     * Dit is de test die er het meest toe doet. Dit portaal heeft één
     * account; zou een terugzetting daaraan komen, dan is er niemand meer
     * die kan inloggen -- ook wij niet.
     */
    public function test_the_account_is_never_touched(): void
    {
        $eigenaar = $this->eigenaar();
        $voor = $eigenaar->only(['email', 'password', 'two_factor_secret', 'name']);

        $backup = $this->maak();

        $eigenaar->forceFill(['name' => 'Nieuwe naam'])->save();

        $this->zetTerug($backup);

        $na = $eigenaar->refresh();

        $this->assertSame($voor['email'], $na->email);
        $this->assertSame($voor['password'], $na->password);
        $this->assertSame($voor['two_factor_secret'], $na->two_factor_secret);

        // De naamswijziging blijft ook staan: het account valt buiten de
        // back-up, dus hij wordt niet teruggedraaid.
        $this->assertSame('Nieuwe naam', $na->name);
    }

    /**
     * De berichten van bezoekers blijven staan, mét hun onderwerp.
     *
     * Dit is het geval waarvoor het terugzetten per rij vergelijkt in
     * plaats van de tabel leeg te maken. `contact_submissions.subject_id`
     * wijst met `nullOnDelete` naar `contact_subjects`: alles weggooien
     * zou bij élke aanvraag het onderwerp op `null` zetten.
     */
    public function test_visitor_messages_keep_their_subject(): void
    {
        $onderwerp = ContactSubject::factory()->create();

        $backup = $this->maak();

        $aanvraag = ContactSubmission::factory()->create([
            'subject_id' => $onderwerp->id,
        ]);

        $this->zetTerug($backup);

        $this->assertDatabaseHas('contact_submissions', [
            'id' => $aanvraag->id,
            'subject_id' => $onderwerp->id,
        ]);
    }

    public function test_visitor_messages_are_not_resurrected(): void
    {
        ContactSubmission::factory()->create(['name' => 'Oud bericht']);

        $backup = $this->maak();

        ContactSubmission::query()->delete();

        $this->zetTerug($backup);

        $this->assertSame(
            0,
            ContactSubmission::query()->count(),
            'Een gewist bericht van een bezoeker is weer tot leven gewekt.',
        );
    }

    /**
     * Er komen geen honderden regels in het activiteitenlogboek.
     *
     * Zonder `Model::withoutEvents()` zou `LogsActivity` voor elke rij
     * een regel schrijven -- en dan is dat logboek onleesbaar voor wat de
     * eigenaar als één handeling heeft gedaan.
     */
    public function test_the_activity_log_is_not_flooded(): void
    {
        Project::factory()->count(5)->create();

        $backup = $this->maak();

        Project::factory()->count(5)->create();

        $voor = DB::table('activity_entries')->count();

        $this->zetTerug($backup);

        $this->assertSame($voor, DB::table('activity_entries')->count());
    }

    /* --- De veiligheidskopie en de sloten ------------------------------------ */

    public function test_restoring_leaves_a_safety_copy_behind(): void
    {
        $eigenaar = $this->eigenaar();

        Project::factory()->create(['title_nl' => 'Oude stand']);
        $backup = $this->maak();

        Project::factory()->create(['title_nl' => 'Nieuwe stand']);

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.terugzetten', $backup), [
                'code' => $this->code(),
                'bevestigcode' => $backup->bevestigcode,
            ])
            ->assertSessionHasNoErrors();

        $kopie = Backup::query()->vanSoort(Backup::SOORT_AUTOMATISCH)->first();

        $this->assertNotNull($kopie, 'Er is geen veiligheidskopie gemaakt.');

        // En die kopie bevat de stand van vlak vóór het terugzetten.
        $this->assertSame(2, $kopie->aantallen['projects']);
        $this->assertSame(1, Project::query()->count());
    }

    public function test_the_safety_copy_brings_the_newer_state_back(): void
    {
        $eigenaar = $this->eigenaar();

        Project::factory()->create(['title_nl' => 'Oude stand']);
        $oud = $this->maak();

        Project::factory()->create(['title_nl' => 'Nieuwe stand']);

        $this->actingAs($eigenaar)->post(route('admin.backups.terugzetten', $oud), [
            'code' => $this->code(),
            'bevestigcode' => $oud->bevestigcode,
        ]);

        $this->assertSame(1, Project::query()->count());

        $kopie = Backup::query()->vanSoort(Backup::SOORT_AUTOMATISCH)->firstOrFail();

        $this->zetTerug($kopie);

        $this->assertSame(2, Project::query()->count());
        $this->assertNotNull(Project::query()->where('title_nl', 'Nieuwe stand')->first());
    }

    /* --- Een back-up van een ander schema ------------------------------------ */

    /**
     * Een veld dat niet meer bestaat wordt overgeslagen, niet fataal.
     *
     * Dit is het geval van een back-up van jaren terug: het schema is
     * verschoven. Hij hoort gewoon terug te kunnen, met een melding erbij
     * over wat er niet paste.
     */
    public function test_a_field_that_no_longer_exists_is_skipped(): void
    {
        $project = Project::factory()->create(['title_nl' => 'Oud project']);

        $backup = $this->maak();

        $lezer = app(BackupLezer::class);
        $pad = (string) $backup->pad();
        $manifest = $lezer->manifest($pad);
        $data = $lezer->data($pad, (string) $manifest['checksum']);

        /*
         * Een echte rij uit de back-up, met een veld erbij dat er toen
         * wel was en nu niet meer. Een verzonnen rij zou hier niet
         * deugen: die mist de verplichte kolommen, en dan toets je de
         * database in plaats van het terugzetten.
         */
        $data['projects'][0]['een_veld_van_toen'] = 'weg';

        $meldingen = app(BackupTerugzetter::class)->zetTerug($data);

        $this->assertSame('Oud project', $project->refresh()->title_nl);

        $this->assertTrue(
            collect($meldingen)->contains(
                fn (string $m): bool => str_contains($m, 'een_veld_van_toen'),
            ),
            'Het overgeslagen veld is niet gemeld.',
        );
    }

    public function test_a_module_that_did_not_exist_yet_is_left_alone(): void
    {
        Project::factory()->create();

        $backup = $this->maak();

        $lezer = app(BackupLezer::class);
        $pad = (string) $backup->pad();
        $manifest = $lezer->manifest($pad);
        $data = $lezer->data($pad, (string) $manifest['checksum']);

        // Doen alsof Projecten nog niet bestond toen deze back-up werd
        // gemaakt: dan hoort hij ongemoeid te blijven.
        unset($data['projects']);

        $meldingen = app(BackupTerugzetter::class)->zetTerug($data);

        $this->assertSame(1, Project::query()->count());

        $this->assertTrue(
            collect($meldingen)->contains(
                fn (string $m): bool => str_contains($m, 'projects'),
            ),
        );
    }
}
