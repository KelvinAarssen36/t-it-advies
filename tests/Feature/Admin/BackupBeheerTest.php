<?php

namespace Tests\Feature\Admin;

use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use App\Models\Backup;
use App\Models\Project;
use App\Models\User;
use App\Support\Backup\BackupMaker;
use App\Support\Backup\Opruimer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Het scherm Beheer → Back-ups: wie erbij mag en wat er vastligt.
 *
 * De sloten op het terugzetten en het verwijderen zijn het onderwerp.
 * Daarbij geldt hetzelfde als bij de schakelaar voor de passkey-stap: de
 * code zit in het verzoek en niet in `2fa.confirm`, want die middleware
 * kan een POST niet onthouden.
 *
 * Zie docs/operations/back-ups.md.
 */
class BackupBeheerTest extends TestCase
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

    private function maak(string $soort = Backup::SOORT_HANDMATIG): Backup
    {
        return app(BackupMaker::class)->maak($soort);
    }

    /* --- Rechten ------------------------------------------------------------ */

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('admin.backups.index'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $zonder = User::factory()->create();

        $this->actingAs($zonder)
            ->get(route('admin.backups.index'))
            ->assertForbidden();

        $this->actingAs($zonder)
            ->post(route('admin.backups.store'))
            ->assertForbidden();
    }

    public function test_the_screen_shows_the_list(): void
    {
        $eigenaar = $this->eigenaar();
        $backup = $this->maak();

        $this->actingAs($eigenaar)
            ->get(route('admin.backups.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Backups')
                ->has('backups', 1)
                ->where('backups.0.naam', $backup->naam)
                ->where('cijfers.nooitGedownload', true)
            );
    }

    /* --- De sloten op het terugzetten ---------------------------------------- */

    public function test_restoring_without_a_code_does_nothing(): void
    {
        $eigenaar = $this->eigenaar();
        Project::factory()->create();
        $backup = $this->maak();
        Project::factory()->create();

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.terugzetten', $backup), ['bevestigcode' => $backup->bevestigcode])
            ->assertSessionHasErrors('code');

        $this->assertSame(2, Project::query()->count());
    }

    public function test_restoring_with_a_wrong_code_does_nothing(): void
    {
        $eigenaar = $this->eigenaar();
        Project::factory()->create();
        $backup = $this->maak();
        Project::factory()->create();

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.terugzetten', $backup), [
                'bevestigcode' => $backup->bevestigcode,
                'code' => '000000',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(2, Project::query()->count());
    }

    /**
     * De overgetypte cijfers moeten kloppen, en dat gaat vóór de code.
     *
     * Die volgorde is geen detail: een TOTP-code werkt één keer, dus een
     * typefout in het overtypen mag geen geldige code opbranden.
     */
    public function test_wrong_typed_digits_do_nothing_and_spare_the_code(): void
    {
        $eigenaar = $this->eigenaar();
        Project::factory()->create();
        $backup = $this->maak();
        Project::factory()->create();

        $code = $this->code();

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.terugzetten', $backup), [
                'bevestigcode' => '0000',
                'code' => $code,
            ])
            ->assertSessionHasErrors('bevestigcode');

        $this->assertSame(2, Project::query()->count());

        // En dezelfde code doet het daarna nog, want hij is niet gebruikt.
        $this->actingAs($eigenaar)
            ->post(route('admin.backups.terugzetten', $backup), [
                'bevestigcode' => $backup->bevestigcode,
                'code' => $code,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Project::query()->count());
    }

    /** Elke back-up heeft zijn eigen cijfers, en geen twee dezelfde. */
    public function test_every_backup_has_its_own_four_digits(): void
    {
        $this->eigenaar();

        $codes = [];

        for ($i = 0; $i < 3; $i++) {
            $this->travel(1)->minute();
            $codes[] = $this->maak()->bevestigcode;
        }

        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^\d{4}$/', (string) $code);
        }

        $this->assertSame($codes, array_unique($codes));
    }

    public function test_restoring_is_logged(): void
    {
        $eigenaar = $this->eigenaar();
        $backup = $this->maak();

        $this->actingAs($eigenaar)->post(route('admin.backups.terugzetten', $backup), [
            'bevestigcode' => $backup->bevestigcode,
            'code' => $this->code(),
        ]);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::BackupTeruggezet->value,
            'outcome' => SecurityOutcome::Success->value,
            'user_id' => $eigenaar->id,
        ]);
    }

    /* --- Verwijderen ---------------------------------------------------------- */

    public function test_deleting_needs_a_code_too(): void
    {
        $eigenaar = $this->eigenaar();
        $backup = $this->maak();

        $this->actingAs($eigenaar)
            ->delete(route('admin.backups.destroy', $backup), ['code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertNotNull($backup->fresh());
    }

    public function test_deleting_with_a_code_removes_the_file_too(): void
    {
        $eigenaar = $this->eigenaar();
        $backup = $this->maak();
        $bestand = $backup->bestand;

        $this->actingAs($eigenaar)
            ->delete(route('admin.backups.destroy', $backup), ['code' => $this->code()])
            ->assertSessionHasNoErrors();

        $this->assertNull($backup->fresh());
        Storage::disk('local')->assertMissing($bestand);
    }

    /* --- Vastzetten ------------------------------------------------------------ */

    /**
     * Een vastgezette back-up telt niet mee voor het maximum.
     *
     * Dat is het hele punt van vastzetten: de goede stand van vlak
     * voordat je iets verprutste mag niet in de weg gaan zitten, en ook
     * niet verdwijnen.
     */
    public function test_a_pinned_backup_does_not_count_towards_the_limit(): void
    {
        $this->eigenaar();

        $vast = $this->maak();
        $vast->forceFill(['vastgezet' => true])->save();

        for ($i = 0; $i < Backup::MAXIMUM_EIGEN; $i++) {
            $this->travel(1)->minute();
            $this->maak();
        }

        $this->assertNotNull($vast->fresh());

        $this->assertSame(Backup::MAXIMUM_EIGEN, Backup::eigenInGebruik());
        $this->assertTrue(Backup::isVol());
    }

    public function test_there_is_a_limit_to_pinning(): void
    {
        $eigenaar = $this->eigenaar();

        $backups = [];

        for ($i = 0; $i <= Backup::MAXIMUM_VAST; $i++) {
            $this->travel(1)->minute();
            $backups[] = $this->maak();
        }

        foreach (array_slice($backups, 0, Backup::MAXIMUM_VAST) as $backup) {
            $this->actingAs($eigenaar)
                ->put(route('admin.backups.vastzetten', $backup), ['vastgezet' => true])
                ->assertSessionHasNoErrors();
        }

        $this->actingAs($eigenaar)
            ->put(route('admin.backups.vastzetten', end($backups)), ['vastgezet' => true])
            ->assertSessionHasErrors('vastgezet');
    }

    /* --- Opruimen --------------------------------------------------------------- */

    /**
     * Bij een volle lijst verdwijnt er niets vanzelf.
     *
     * **Dit ging eerst wel vanzelf**, en dat was fout: je maakt even een
     * back-up voor de zekerheid en raakt daarmee de back-up kwijt die je
     * eigenlijk wilde bewaren.
     */
    public function test_a_full_list_refuses_instead_of_throwing_the_oldest_away(): void
    {
        $eigenaar = $this->eigenaar();

        $oudste = $this->maak();

        for ($i = 1; $i < Backup::MAXIMUM_EIGEN; $i++) {
            $this->travel(1)->minute();
            $this->maak();
        }

        $this->assertTrue(Backup::isVol());

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.store'))
            ->assertSessionHasErrors('vervang');

        $this->assertNotNull($oudste->fresh(), 'De oudste is stilletjes weggegooid.');
        $this->assertSame(Backup::MAXIMUM_EIGEN, Backup::query()->count());
    }

    /** Kiest de eigenaar er een, dan maakt die plaats voor de nieuwe. */
    public function test_choosing_one_makes_room(): void
    {
        $eigenaar = $this->eigenaar();

        $oudste = $this->maak();

        for ($i = 1; $i < Backup::MAXIMUM_EIGEN; $i++) {
            $this->travel(1)->minute();
            $this->maak();
        }

        $this->travel(1)->minute();

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.store'), [
                'vervang' => [$oudste->id],
                'code' => $this->code(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertNull($oudste->fresh());
        $this->assertSame(Backup::MAXIMUM_EIGEN, Backup::eigenInGebruik());
    }

    /** Maar een vastgezette mag je niet als offer aanwijzen. */
    public function test_a_pinned_backup_cannot_be_sacrificed(): void
    {
        $eigenaar = $this->eigenaar();

        $vast = $this->maak();
        $vast->forceFill(['vastgezet' => true])->save();

        for ($i = 0; $i < Backup::MAXIMUM_EIGEN; $i++) {
            $this->travel(1)->minute();
            $this->maak();
        }

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.store'), [
                'vervang' => [$vast->id],
                'code' => $this->code(),
            ])
            ->assertSessionHasErrors('vervang');

        $this->assertNotNull($vast->fresh());
    }

    /**
     * De veiligheidskopieën ruimen zichzelf wél op.
     *
     * Die ontstaan midden in een terugzetting, en daar hoort geen vraag
     * doorheen te komen.
     */
    public function test_safety_copies_still_clean_up_after_themselves(): void
    {
        $this->eigenaar();

        $oudste = $this->maak(Backup::SOORT_AUTOMATISCH);

        for ($i = 0; $i < Backup::MAXIMUM_AUTOMATISCH; $i++) {
            $this->travel(1)->minute();
            $this->maak(Backup::SOORT_AUTOMATISCH);
        }

        app(Opruimer::class)->ruimOp();

        $this->assertNull($oudste->fresh());

        $this->assertSame(
            Backup::MAXIMUM_AUTOMATISCH,
            Backup::query()->vanSoort(Backup::SOORT_AUTOMATISCH)->count(),
        );
    }

    /** En ze tellen niet mee in het maximum van de eigenaar. */
    public function test_safety_copies_do_not_count_towards_the_limit(): void
    {
        $this->eigenaar();

        $this->maak();
        $this->travel(1)->minute();
        $this->maak(Backup::SOORT_AUTOMATISCH);

        $this->assertSame(1, Backup::eigenInGebruik());
        $this->assertFalse(Backup::isVol());
    }

    /**
     * Een beeld blijft liggen zolang een andere back-up hem nodig heeft.
     *
     * Dit is de keerzijde van het delen van beelden: zou het opruimen
     * blind de beelden van de oudste weggooien, dan maakt het opruimen
     * van de oudste de nieuwste kapot.
     */
    public function test_an_image_that_another_backup_still_needs_stays(): void
    {
        $this->eigenaar();

        $pad = 'projecten/voorbeeld.webp';
        $bestand = UploadedFile::fake()->image('beeld.webp', 64, 64);
        Storage::disk('public')->put($pad, (string) $bestand->getContent());
        Project::factory()->create(['image_path' => $pad]);

        $oudste = $this->maak();
        $this->travel(1)->minute();
        $this->maak();

        Storage::disk('local')->delete($oudste->bestand);
        $oudste->delete();

        app(Opruimer::class)->ruimBeeldenOp();

        $hash = hash('sha256', (string) Storage::disk('public')->get($pad));

        Storage::disk('local')->assertExists(Backup::BEELDMAP.'/'.$hash.'.webp');
    }

    public function test_an_image_nobody_needs_any_more_goes_away(): void
    {
        $this->eigenaar();

        $pad = 'projecten/voorbeeld.webp';
        $bestand = UploadedFile::fake()->image('beeld.webp', 64, 64);
        Storage::disk('public')->put($pad, (string) $bestand->getContent());
        $project = Project::factory()->create(['image_path' => $pad]);

        $backup = $this->maak();

        Storage::disk('local')->delete($backup->bestand);
        $backup->delete();
        $project->delete();

        app(Opruimer::class)->ruimBeeldenOp();

        $this->assertSame([], Storage::disk('local')->files(Backup::BEELDMAP));
    }

    /* --- De datum waar het om gaat --------------------------------------- */

    /**
     * Een geüploade back-up houdt de datum van zijn inhoud.
     *
     * **Dit ging mis.** De rij kreeg `created_at = nu`, dus een bestand
     * van januari dat je vandaag terugplaatst stond bovenaan met het
     * merkje "Nieuwste" -- terwijl de naam januari zei.
     */
    public function test_an_uploaded_backup_keeps_the_date_of_its_contents(): void
    {
        $eigenaar = $this->eigenaar();

        $this->travel(-30)->days();
        $oud = $this->maak();
        $toen = $oud->vastgelegd_op;

        $pad = (string) $oud->pad();
        $kopie = sys_get_temp_dir().'/backup-datum-'.uniqid().'.zip';
        copy($pad, $kopie);

        Storage::disk('local')->delete($oud->bestand);
        $oud->delete();

        $this->travelBack();

        $this->actingAs($eigenaar)->post(route('admin.backups.upload'), [
            'bestand' => new UploadedFile($kopie, 'back-up.zip', 'application/zip', null, true),
        ])->assertSessionHasNoErrors();

        $geupload = Backup::query()->vanSoort(Backup::SOORT_GEUPLOAD)->firstOrFail();

        $this->assertSame(
            $toen->toDateTimeString(),
            $geupload->vastgelegd_op->toDateTimeString(),
            'De geüploade back-up draagt de datum van vandaag in plaats van die van zijn inhoud.',
        );
    }

    /** En hij staat daardoor op zijn eigen plek in de lijst, niet bovenaan. */
    public function test_an_old_uploaded_backup_is_not_the_newest(): void
    {
        $eigenaar = $this->eigenaar();

        $this->travel(-30)->days();
        $oud = $this->maak();

        $pad = (string) $oud->pad();
        $kopie = sys_get_temp_dir().'/backup-datum-'.uniqid().'.zip';
        copy($pad, $kopie);

        Storage::disk('local')->delete($oud->bestand);
        $oud->delete();

        $this->travelBack();

        $vers = $this->maak();

        $this->actingAs($eigenaar)->post(route('admin.backups.upload'), [
            'bestand' => new UploadedFile($kopie, 'back-up.zip', 'application/zip', null, true),
        ]);

        $this->actingAs($eigenaar)
            ->get(route('admin.backups.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('backups.0.naam', $vers->naam)
                ->where('backups.0.nieuwste', true)
                ->where('backups.1.oudste', true)
            );
    }

    /* --- De merkjes -------------------------------------------------------- */

    /** De oudste is altijd te zien, ook als de lijst nog niet vol is. */
    public function test_the_oldest_is_marked_even_when_the_list_is_not_full(): void
    {
        $eigenaar = $this->eigenaar();

        $oudste = $this->maak();
        $this->travel(1)->minute();
        $this->maak();

        $this->assertFalse(Backup::isVol());

        $this->actingAs($eigenaar)
            ->get(route('admin.backups.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('backups.1.naam', $oudste->naam)
                ->where('backups.1.oudste', true)
                ->where('backups.0.nieuwste', true)
            );
    }

    /** Bij één back-up staat er geen merkje: die is allebei tegelijk. */
    public function test_a_single_backup_gets_no_oldest_badge(): void
    {
        $eigenaar = $this->eigenaar();
        $this->maak();

        $this->actingAs($eigenaar)
            ->get(route('admin.backups.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('backups.0.oudste', false)
            );
    }

    /* --- Er staan er te veel ------------------------------------------------ */

    /**
     * Boven de grens komen kan, en dan moet het opvallen.
     *
     * De weg erheen: zet er twee vast terwijl je er vijf hebt --
     * vastgezette tellen niet mee -- en laat ze daarna los. Dan staan er
     * zeven die meetellen.
     */
    public function test_releasing_pinned_backups_can_push_you_over_the_limit(): void
    {
        $eigenaar = $this->eigenaar();

        $vast = [];

        for ($i = 0; $i < 2; $i++) {
            $this->travel(1)->minute();
            $backup = $this->maak();
            $backup->forceFill(['vastgezet' => true])->save();
            $vast[] = $backup;
        }

        for ($i = 0; $i < Backup::MAXIMUM_EIGEN; $i++) {
            $this->travel(1)->minute();
            $this->maak();
        }

        $this->assertSame(0, Backup::teveel());

        foreach ($vast as $backup) {
            $this->actingAs($eigenaar)
                ->put(route('admin.backups.vastzetten', $backup), ['vastgezet' => false]);
        }

        $this->assertSame(2, Backup::teveel());

        // En het scherm zegt het, met het juiste aantal.
        $this->actingAs($eigenaar)
            ->get(route('admin.backups.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.teveel', 2)
            );
    }

    /** Bij een teveel krijgen de twee oudste allebei het merkje. */
    public function test_both_oldest_are_marked_when_two_must_go(): void
    {
        $eigenaar = $this->eigenaar();

        for ($i = 0; $i < Backup::MAXIMUM_EIGEN + 2; $i++) {
            $this->travel(1)->minute();
            $this->maak();
        }

        $this->assertSame(2, Backup::teveel());

        $this->actingAs($eigenaar)
            ->get(route('admin.backups.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('backups.5.oudste', true)
                ->where('backups.6.oudste', true)
                ->where('backups.4.oudste', false)
            );
    }

    public function test_cleaning_up_brings_the_list_back_within_the_limit(): void
    {
        $eigenaar = $this->eigenaar();

        $ids = [];

        for ($i = 0; $i < Backup::MAXIMUM_EIGEN + 2; $i++) {
            $this->travel(1)->minute();
            $ids[] = $this->maak()->id;
        }

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.opruimen'), [
                'ids' => array_slice($ids, 0, 2),
                'code' => $this->code(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0, Backup::teveel());
        $this->assertSame(Backup::MAXIMUM_EIGEN, Backup::eigenInGebruik());
    }

    public function test_cleaning_up_needs_the_code(): void
    {
        $eigenaar = $this->eigenaar();

        $ids = [];

        for ($i = 0; $i < Backup::MAXIMUM_EIGEN + 2; $i++) {
            $this->travel(1)->minute();
            $ids[] = $this->maak()->id;
        }

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.opruimen'), [
                'ids' => array_slice($ids, 0, 2),
                'code' => '000000',
            ])
            ->assertSessionHasErrors('code');

        $this->assertSame(2, Backup::teveel());
    }

    public function test_cleaning_up_too_few_is_refused(): void
    {
        $eigenaar = $this->eigenaar();

        $ids = [];

        for ($i = 0; $i < Backup::MAXIMUM_EIGEN + 2; $i++) {
            $this->travel(1)->minute();
            $ids[] = $this->maak()->id;
        }

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.opruimen'), [
                'ids' => [$ids[0]],
                'code' => $this->code(),
            ])
            ->assertSessionHasErrors('ids');

        $this->assertSame(2, Backup::teveel());
    }

    /**
     * Plaats maken vraagt ook om de code.
     *
     * Zonder dat is "een nieuwe maken" een sluiproute om de beveiliging
     * op verwijderen te omzeilen.
     */
    public function test_making_room_needs_the_code_too(): void
    {
        $eigenaar = $this->eigenaar();

        $oudste = $this->maak();

        for ($i = 1; $i < Backup::MAXIMUM_EIGEN; $i++) {
            $this->travel(1)->minute();
            $this->maak();
        }

        $this->actingAs($eigenaar)
            ->post(route('admin.backups.store'), [
                'vervang' => [$oudste->id],
                'code' => '000000',
            ])
            ->assertSessionHasErrors('code');

        $this->assertNotNull($oudste->fresh());
    }

    /* --- Downloaden -------------------------------------------------------------- */

    public function test_downloading_is_remembered(): void
    {
        $eigenaar = $this->eigenaar();
        $backup = $this->maak();

        $this->assertNull($backup->gedownload_op);

        $this->actingAs($eigenaar)
            ->get(route('admin.backups.download', $backup))
            ->assertOk()
            ->assertDownload('website-'.$backup->naam.'.zip');

        $this->assertNotNull(
            $backup->refresh()->gedownload_op,
            'Zonder dit weet het scherm niet dat de back-up ook ergens anders staat.',
        );
    }

    public function test_the_warning_disappears_once_it_is_downloaded(): void
    {
        $eigenaar = $this->eigenaar();
        $backup = $this->maak();

        $this->actingAs($eigenaar)->get(route('admin.backups.download', $backup));

        $this->actingAs($eigenaar)
            ->get(route('admin.backups.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.nooitGedownload', false)
            );
    }
}
