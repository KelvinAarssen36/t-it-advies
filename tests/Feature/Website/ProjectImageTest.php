<?php

namespace Tests\Feature\Website;

use App\Enums\ProjectType;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Het beeld bij een project.
 *
 * De zes vaste gevallen uit docs/development/testen.md, plus de ondergrens
 * van deze module: 240 pixels in plaats van de 48 van een logo. Dit beeld
 * draagt het uitgelichte blok en staat daar groot op het scherm.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
class ProjectImageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);

        Storage::fake(Project::SCHIJF);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $anders
     * @return array<string, mixed>
     */
    private function invoer(array $anders = []): array
    {
        return [
            'type' => ProjectType::Project->value,
            'title_nl' => 'Een project',
            'organisation' => 'Een organisatie',
            'role_nl' => 'Adviseur',
            'started_on' => '2023-01',
            'summary_nl' => 'Een korte samenvatting van het project.',
            'published' => true,
            ...$anders,
        ];
    }

    /**
     * @param  array<string, mixed>  $anders
     */
    private function maak(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())
            ->post(route('website.projecten.store'), $this->invoer($anders));
    }

    /* --- Een beeld opslaan --------------------------------------------------- */

    public function test_an_image_is_stored(): void
    {
        $this->maak(['beeld' => UploadedFile::fake()->image('logo.png', 800, 800)])
            ->assertSessionHasNoErrors();

        $project = Project::query()->sole();

        $this->assertNotNull($project->image_path);
        Storage::disk(Project::SCHIJF)->assertExists((string) $project->image_path);
    }

    /** In de eigen map, zodat het beheer en de opruiming dezelfde plek bedoelen. */
    public function test_it_lands_in_the_project_folder(): void
    {
        $this->maak(['beeld' => UploadedFile::fake()->image('logo.png', 800, 800)]);

        $this->assertStringStartsWith(
            Project::MAP.'/',
            (string) Project::query()->sole()->image_path,
        );
    }

    /**
     * De naam van de klant wordt niet gebruikt.
     *
     * Een verzonnen bestandsnaam dus: anders kan een bezoeker uit het adres
     * aflezen hoe het bestand op de computer van de eigenaar heette, en een
     * naam met puntjes erin is bovendien een pad.
     */
    public function test_the_name_of_the_upload_is_not_used(): void
    {
        $this->maak([
            'beeld' => UploadedFile::fake()->image('../../.env.png', 800, 800),
        ]);

        $pad = (string) Project::query()->sole()->image_path;

        $this->assertStringNotContainsString('..', $pad);
        $this->assertStringNotContainsString('.env', $pad);
    }

    /* --- Wat er wordt geweigerd ---------------------------------------------- */

    public function test_something_that_is_not_an_image_is_refused(): void
    {
        $this->maak(['beeld' => UploadedFile::fake()->create('handleiding.pdf', 100)])
            ->assertSessionHasErrors('beeld');

        $this->assertSame(0, Project::query()->count());
    }

    /**
     * Een te klein beeld wordt geweigerd.
     *
     * De grens ligt hier op 240 en niet op de 48 van een logo: dit beeld
     * vult het uitgelichte blok, en 100 pixels is daar een vlek.
     */
    public function test_an_image_that_is_too_small_is_refused(): void
    {
        $this->maak(['beeld' => UploadedFile::fake()->image('klein.png', 100, 100)])
            ->assertSessionHasErrors('beeld');

        $this->assertSame(0, Project::query()->count());
    }

    public function test_an_image_that_is_too_large_is_refused(): void
    {
        $this->maak([
            'beeld' => UploadedFile::fake()->image('groot.png', 800, 800)->size(4096),
        ])->assertSessionHasErrors('beeld');

        $this->assertSame(0, Project::query()->count());
    }

    /* --- Wijzigen ------------------------------------------------------------ */

    /**
     * Een opslag zonder bestand laat het beeld staan.
     *
     * Zou het veld er altijd in zitten, dan raakt de eigenaar zijn beeld
     * kwijt zodra hij een woord in zijn samenvatting verbetert.
     */
    public function test_a_save_without_a_file_keeps_the_image(): void
    {
        $this->maak(['beeld' => UploadedFile::fake()->image('logo.png', 800, 800)]);

        $project = Project::query()->sole();
        $pad = (string) $project->image_path;

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.update', $project),
            $this->invoer(['summary_nl' => 'Een verbeterde samenvatting.']),
        )->assertSessionHasNoErrors();

        $this->assertSame($pad, $project->refresh()->image_path);
        Storage::disk(Project::SCHIJF)->assertExists($pad);
    }

    /** Vervangen gooit het oude bestand weg. */
    public function test_replacing_the_image_removes_the_old_file(): void
    {
        $this->maak(['beeld' => UploadedFile::fake()->image('eerste.png', 800, 800)]);

        $project = Project::query()->sole();
        $oud = (string) $project->image_path;

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.update', $project),
            $this->invoer([
                'beeld' => UploadedFile::fake()->image('tweede.png', 800, 800),
            ]),
        )->assertSessionHasNoErrors();

        $nieuw = (string) $project->refresh()->image_path;

        $this->assertNotSame($oud, $nieuw);
        Storage::disk(Project::SCHIJF)->assertMissing($oud);
        Storage::disk(Project::SCHIJF)->assertExists($nieuw);
    }

    /** En weghalen laat het project zonder beeld achter, niet zonder project. */
    public function test_the_image_can_be_removed(): void
    {
        $this->maak(['beeld' => UploadedFile::fake()->image('logo.png', 800, 800)]);

        $project = Project::query()->sole();
        $pad = (string) $project->image_path;

        $this->actingAs($this->beheerder())->put(
            route('website.projecten.update', $project),
            $this->invoer(['beeld_verwijderen' => true]),
        )->assertSessionHasNoErrors();

        $this->assertNull($project->refresh()->image_path);
        $this->assertNull($project->beeld());
        Storage::disk(Project::SCHIJF)->assertMissing($pad);
    }

    /* --- Verwijderen --------------------------------------------------------- */

    public function test_removing_the_project_takes_the_file_with_it(): void
    {
        $this->maak(['beeld' => UploadedFile::fake()->image('logo.png', 800, 800)]);

        $project = Project::query()->sole();
        $pad = (string) $project->image_path;

        $this->actingAs($this->beheerder())
            ->delete(route('website.projecten.destroy', $project));

        Storage::disk(Project::SCHIJF)->assertMissing($pad);
    }

    /* --- Zonder beeld -------------------------------------------------------- */

    /**
     * Een project zonder beeld werkt gewoon.
     *
     * Er is geen standaardbeeld: de kaart vult die plek met de eerste
     * letter van de organisatie. Een verzonnen plaatje zou suggereren dat
     * er iets is.
     */
    public function test_a_project_without_an_image_still_works(): void
    {
        $this->maak();

        $project = Project::query()->sole();

        $this->assertNull($project->beeld());
        $this->assertSame('E', $project->voorDeKaart()['letter']);
    }
}
