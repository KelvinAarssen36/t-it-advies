<?php

namespace Tests\Feature\Website;

use App\Enums\ActivityAction;
use App\Models\ActivityEntry;
use App\Models\Certificate;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De certificaten: aanmaken, wijzigen, verwijderen.
 *
 * De lijst met wat een CRUD moet afdekken staat in
 * docs/development/testen.md; dit bestand loopt hem af. Het verlopen en
 * het online zetten hebben hun eigen bestanden, want daar zit genoeg
 * eigen gedrag in om het hier onleesbaar te maken.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
class CertificateCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * Een volledig geldig formulier, waarvan je stukjes kunt vervangen.
     *
     * @param  array<string, mixed>  $anders
     * @return array<string, mixed>
     */
    private function invoer(array $anders = []): array
    {
        return array_merge([
            'published' => true,
            'title_nl' => 'Azure-beheerder',
            'title_en' => 'Azure Administrator',
            'issuer' => 'Microsoft',
            'issued_on' => '2024-03',
            'expires_on' => '',
            'credential_id' => 'AZ-104-99887766',
            'body_nl' => 'Inrichten en beheren van Azure.',
            'body_en' => 'Setting up and managing Azure.',
        ], $anders);
    }

    /**
     * @param  array<string, mixed>  $anders
     */
    private function maak(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())
            ->post(route('website.certificaten.store'), $this->invoer($anders));
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $certificaat = Certificate::factory()->create();

        $this->get(route('website.certificaten.index'))->assertRedirect(route('login'));
        $this->post(route('website.certificaten.store'))->assertRedirect(route('login'));
        $this->put(route('website.certificaten.update', $certificaat))->assertRedirect(route('login'));
        $this->put(route('website.certificaten.kop'))->assertRedirect(route('login'));
        $this->put(route('website.certificaten.volgorde'))->assertRedirect(route('login'));
        $this->patch(route('website.certificaten.online', $certificaat))->assertRedirect(route('login'));
        $this->delete(route('website.certificaten.destroy', $certificaat))->assertRedirect(route('login'));

        // En er is niets gebeurd.
        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_every_route_needs_the_permission(): void
    {
        $certificaat = Certificate::factory()->create();
        $gebruiker = User::factory()->create();

        $this->actingAs($gebruiker)->get(route('website.certificaten.index'))->assertForbidden();
        $this->actingAs($gebruiker)->post(route('website.certificaten.store'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.certificaten.update', $certificaat))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.certificaten.kop'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.certificaten.volgorde'))->assertForbidden();
        $this->actingAs($gebruiker)->patch(route('website.certificaten.online', $certificaat))->assertForbidden();
        $this->actingAs($gebruiker)->delete(route('website.certificaten.destroy', $certificaat))->assertForbidden();

        $this->assertSame(1, Certificate::query()->count());
    }

    public function test_the_screen_lists_the_certificates(): void
    {
        Certificate::factory()->count(3)->create();

        $this->actingAs($this->beheerder())
            ->get(route('website.certificaten.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Certificaten')
                ->has('items', 3)
                ->has('opleidingen', 0)
                ->has('opties.maanden', 12)
                ->has('opties.jaren')
                ->has('opties.jarenVooruit')
                ->where('leeg', false)
                ->where('kanVertalen', false)
                ->etc());
    }

    public function test_creating_stores_both_languages(): void
    {
        $this->maak()->assertSessionHasNoErrors();

        $certificaat = Certificate::query()->firstOrFail();

        $this->assertSame('Azure-beheerder', $certificaat->title_nl);
        $this->assertSame('Azure Administrator', $certificaat->title_en);
        $this->assertSame('Microsoft', $certificaat->issuer);
        $this->assertSame('AZ-104-99887766', $certificaat->credential_id);
        $this->assertSame('Inrichten en beheren van Azure.', $certificaat->body_nl);
        $this->assertSame('Setting up and managing Azure.', $certificaat->body_en);
    }

    /**
     * "2024-03" wordt 1 maart 2024.
     *
     * De dag betekent niets; hij staat er omdat een datumkolom er een
     * nodig heeft. Zie de trait SchoneVelden.
     */
    public function test_the_month_becomes_the_first_of_that_month(): void
    {
        $this->maak();

        $this->assertSame(
            '2024-03-01',
            Certificate::query()->firstOrFail()->issued_on->toDateString(),
        );
    }

    public function test_a_new_certificate_goes_to_the_end_of_the_row(): void
    {
        // Waar hij komt te staan bepaalt de eigenaar daarna met slepen;
        // hem ergens tussen zetten zou een keuze zijn die wij maken.
        Certificate::factory()->create(['position' => 7]);

        $this->maak();

        $this->assertSame(
            8,
            (int) Certificate::query()->latest('id')->value('position'),
        );
    }

    public function test_an_empty_optional_field_becomes_null_and_not_an_empty_string(): void
    {
        $this->maak([
            'title_en' => '',
            'expires_on' => '',
            'credential_id' => '',
            'body_nl' => '',
            'body_en' => '',
        ])->assertSessionHasNoErrors();

        $certificaat = Certificate::query()->firstOrFail();

        $this->assertNull($certificaat->title_en);
        $this->assertNull($certificaat->expires_on);
        $this->assertNull($certificaat->credential_id);
        $this->assertNull($certificaat->body_nl);
        $this->assertNull($certificaat->body_en);
    }

    public function test_the_name_the_issuer_and_the_date_are_required(): void
    {
        $this->maak(['title_nl' => '', 'issuer' => '', 'issued_on' => ''])
            ->assertSessionHasErrors(['title_nl', 'issuer', 'issued_on']);

        // En er is niets in de database beland.
        $this->assertSame(0, Certificate::query()->count());
    }

    /**
     * Een geldigheid die afloopt voordat het certificaat behaald werd.
     *
     * Zonder deze regel kun je dat invoeren, en dan staat er op de
     * website een certificaat dat verlopen is voordat het bestond.
     */
    public function test_an_expiry_before_the_issue_date_is_refused(): void
    {
        $this->maak(['issued_on' => '2024-03', 'expires_on' => '2023-01'])
            ->assertSessionHasErrors('expires_on');

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_a_broken_month_is_refused(): void
    {
        $this->maak(['issued_on' => 'maart 2024'])
            ->assertSessionHasErrors('issued_on');

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_text_that_is_too_long_is_refused(): void
    {
        $this->maak([
            'title_nl' => str_repeat('a', 121),
            'issuer' => str_repeat('b', 121),
            'credential_id' => str_repeat('c', 121),
            'body_nl' => str_repeat('d', 2001),
        ])->assertSessionHasErrors([
            'title_nl',
            'issuer',
            'credential_id',
            'body_nl',
        ]);

        $this->assertSame(0, Certificate::query()->count());
    }

    public function test_changing_one_certificate_leaves_the_others_alone(): void
    {
        $certificaat = Certificate::factory()->create(['title_nl' => 'Eerste']);
        $ander = Certificate::factory()->create(['title_nl' => 'Tweede']);

        $this->actingAs($this->beheerder())->put(
            route('website.certificaten.update', $certificaat),
            $this->invoer(['title_nl' => 'Aangepast']),
        )->assertSessionHasNoErrors();

        $this->assertSame('Aangepast', $certificaat->fresh()?->title_nl);
        $this->assertSame('Tweede', $ander->fresh()?->title_nl);
    }

    public function test_saving_the_same_thing_says_nothing_changed(): void
    {
        $certificaat = Certificate::factory()->create($this->alsModel());

        $this->travel(1)->minute();

        $this->actingAs($this->beheerder())
            ->put(route('website.certificaten.update', $certificaat), $this->invoer())
            ->assertSessionHasNoErrors();

        // Niets veranderd is geen wijziging: geen nieuwe tijdstempel en
        // geen regel in het logboek.
        $this->assertEquals(
            $certificaat->updated_at,
            $certificaat->fresh()?->updated_at,
        );
    }

    public function test_deleting_leaves_the_rest_standing(): void
    {
        $certificaat = Certificate::factory()->create();
        Certificate::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.certificaten.destroy', $certificaat));

        $this->assertSame(1, Certificate::query()->count());
        $this->assertNull($certificaat->fresh());
    }

    /**
     * Het logo gaat mee als het certificaat weggaat.
     *
     * Zonder die haak blijft elk bestand staan van elk certificaat dat
     * ooit is weggegooid. Dat merk je niet -- de site werkt gewoon --
     * tot de schijf vol is.
     */
    public function test_deleting_removes_the_logo_from_the_disk(): void
    {
        Storage::fake(Certificate::SCHIJF);

        $pad = Certificate::MAP.'/merk.png';
        Storage::disk(Certificate::SCHIJF)->put($pad, 'beeld');

        $certificaat = Certificate::factory()->create(['logo_path' => $pad]);

        $this->actingAs($this->beheerder())
            ->delete(route('website.certificaten.destroy', $certificaat));

        Storage::disk(Certificate::SCHIJF)->assertMissing($pad);
    }

    public function test_a_logo_is_stored_and_reaches_the_screen(): void
    {
        Storage::fake(Certificate::SCHIJF);

        $this->actingAs($this->beheerder())->post(
            route('website.certificaten.store'),
            $this->invoer(['logo' => UploadedFile::fake()->image('merk.png', 512, 512)]),
        )->assertSessionHasNoErrors();

        $certificaat = Certificate::query()->firstOrFail();

        $this->assertNotNull($certificaat->logo_path);
        Storage::disk(Certificate::SCHIJF)->assertExists((string) $certificaat->logo_path);
    }

    /**
     * Een opslag zonder nieuw bestand laat het oude logo staan.
     *
     * Anders raakt de eigenaar zijn logo kwijt zodra hij een typefout in
     * de naam verbetert.
     */
    public function test_saving_without_a_file_keeps_the_existing_logo(): void
    {
        Storage::fake(Certificate::SCHIJF);

        $pad = Certificate::MAP.'/merk.png';
        Storage::disk(Certificate::SCHIJF)->put($pad, 'beeld');

        $certificaat = Certificate::factory()->create(['logo_path' => $pad]);

        $this->actingAs($this->beheerder())->put(
            route('website.certificaten.update', $certificaat),
            $this->invoer(['title_nl' => 'Anders']),
        );

        $this->assertSame($pad, $certificaat->fresh()?->logo_path);
    }

    public function test_the_owner_can_remove_the_logo_on_purpose(): void
    {
        Storage::fake(Certificate::SCHIJF);

        $pad = Certificate::MAP.'/merk.png';
        Storage::disk(Certificate::SCHIJF)->put($pad, 'beeld');

        $certificaat = Certificate::factory()->create(['logo_path' => $pad]);

        $this->actingAs($this->beheerder())->put(
            route('website.certificaten.update', $certificaat),
            $this->invoer(['logo_verwijderen' => true]),
        );

        $this->assertNull($certificaat->fresh()?->logo_path);
    }

    public function test_every_change_is_written_to_the_activity_log(): void
    {
        $this->maak();

        $certificaat = Certificate::query()->firstOrFail();

        $this->actingAs($this->beheerder())->put(
            route('website.certificaten.update', $certificaat),
            $this->invoer(['title_nl' => 'Anders']),
        );

        $this->actingAs($this->beheerder())
            ->delete(route('website.certificaten.destroy', $certificaat));

        $regels = ActivityEntry::query()
            ->where('subject_type', Certificate::class)
            ->orderBy('id')
            ->get();

        $this->assertCount(3, $regels);
        $this->assertSame(ActivityAction::Created, $regels[0]->action);
        $this->assertSame(ActivityAction::Updated, $regels[1]->action);
        $this->assertSame(ActivityAction::Deleted, $regels[2]->action);

        // Het label zegt bij welke uitgever het hoorde; daarmee vind je
        // de regel terug zonder het item erbij te halen.
        $this->assertSame('Azure-beheerder (Microsoft)', $regels[0]->subject_label);
    }

    /**
     * Dezelfde gegevens als `invoer()`, maar als modelvelden.
     *
     * @return array<string, mixed>
     */
    private function alsModel(): array
    {
        return [
            'published' => true,
            'title_nl' => 'Azure-beheerder',
            'title_en' => 'Azure Administrator',
            'issuer' => 'Microsoft',
            'issued_on' => '2024-03-01',
            'expires_on' => null,
            'credential_id' => 'AZ-104-99887766',
            'body_nl' => 'Inrichten en beheren van Azure.',
            'body_en' => 'Setting up and managing Azure.',
        ];
    }
}
