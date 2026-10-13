<?php

namespace Tests\Feature\Website;

use App\Models\Experience;
use App\Models\User;
use App\Support\Media\Logo;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Het logo dat de klant bij een ervaring uploadt.
 *
 * Een uploadveld is de enige plek in dit portaal waar een bezoeker een
 * bestand op onze schijf krijgt. Wat hier wordt bewaakt:
 *
 * 1. **Wat eruit komt is een klein vierkant plaatje**, en niet wat de klant
 *    toevallig aanleverde. Anders downloadt elke bezoeker van de website
 *    een schermafdruk van drie megabyte.
 * 2. **De bestandsnaam komt niet van de klant.** Een naam als
 *    `../../.env` of `foto.php.jpg` hoort nooit op de schijf te komen.
 * 3. **Er blijft niets slingeren.** Vervang je een logo of gooi je de hele
 *    ervaring weg, dan gaat het oude bestand mee.
 * 4. **Een gewone opslag raakt je logo niet kwijt.** Dat is de val: wie een
 *    typefout in zijn beschrijving verbetert, stuurt geen bestand mee.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class ExperienceLogoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        // Een neppe schijf: de tests schrijven nooit in storage/app/public.
        Storage::fake(Experience::SCHIJF);
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
            'icon' => 'werk',
            'role_nl' => 'Systeembeheerder',
            'organisation' => 'Eni',
            'started_on' => '2021-03',
            ...$anders,
        ];
    }

    public function test_an_uploaded_logo_becomes_a_small_square(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                // Bewust liggend: er moet een vierkant uit komen.
                'logo' => UploadedFile::fake()->image('beeldmerk.png', 900, 400),
            ]))
            ->assertSessionHasNoErrors();

        $ervaring = Experience::query()->sole();

        $this->assertNotNull($ervaring->logo_path);
        Storage::disk(Experience::SCHIJF)->assertExists($ervaring->logo_path);

        $maten = getimagesizefromstring(
            (string) Storage::disk(Experience::SCHIJF)->get($ervaring->logo_path),
        );

        $this->assertSame(Logo::MAAT, $maten[0]);
        $this->assertSame(Logo::MAAT, $maten[1]);
    }

    /**
     * De kleur van de hoek van het opgeslagen beeld.
     *
     * Daar staat de ondergrond: bij een plaatje dat niet het hele vierkant
     * vult, is de hoek precies de plek waar je ziet wat eronder ligt.
     *
     * @return array{red: int, green: int, blue: int, alpha: int}
     */
    private function hoekkleur(string $pad): array
    {
        $beeld = imagecreatefromstring(
            (string) Storage::disk(Experience::SCHIJF)->get($pad),
        );

        $kleur = imagecolorsforindex($beeld, imagecolorat($beeld, 2, 2));

        imagedestroy($beeld);

        return $kleur;
    }

    public function test_a_white_background_is_baked_into_the_file(): void
    {
        /*
         * De achtergrond zit ín het bestand en niet in een CSS-regel. Dat
         * scheelt de website een uitzondering: elk opgeslagen beeldmerk is
         * daarna een gewoon vierkant plaatje dat overal hetzelfde getoond
         * kan worden. Zonder dit zou een donker logo met doorzichtige
         * randen op de donkere site verdwijnen.
         */
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                // Liggend, dus boven en onder blijft de ondergrond over.
                'logo' => UploadedFile::fake()->image('logo.png', 400, 100),
                'logo_plaat' => true,
            ]))
            ->assertSessionHasNoErrors();

        $kleur = $this->hoekkleur((string) Experience::query()->sole()->logo_path);

        $this->assertSame(255, $kleur['red']);
        $this->assertSame(255, $kleur['green']);
        $this->assertSame(255, $kleur['blue']);
        $this->assertSame(0, $kleur['alpha'], 'De witte plaat hoort dekkend te zijn.');
    }

    public function test_without_the_background_the_corners_stay_transparent(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'logo' => UploadedFile::fake()->image('logo.png', 400, 100),
                'logo_plaat' => false,
            ]))
            ->assertSessionHasNoErrors();

        // 127 is bij GD volledig doorzichtig.
        $this->assertSame(
            127,
            $this->hoekkleur((string) Experience::query()->sole()->logo_path)['alpha'],
        );
    }

    public function test_zooming_in_changes_what_is_stored(): void
    {
        // Zwak maar zinvol: het bewijst dat de uitsnede daadwerkelijk bij
        // GD terechtkomt en niet ergens onderweg wordt vergeten.
        $maak = function (float $zoom): string {
            Experience::query()->delete();

            $this->actingAs($this->beheerder())
                ->post(route('website.ervaring.store'), $this->invoer([
                    'logo' => UploadedFile::fake()->image('logo.png', 400, 200),
                    'logo_zoom' => $zoom,
                ]))
                ->assertSessionHasNoErrors();

            return (string) Storage::disk(Experience::SCHIJF)->get(
                (string) Experience::query()->sole()->logo_path,
            );
        };

        $this->assertNotSame($maak(1.0), $maak(2.0));
    }

    public function test_a_nonsense_crop_is_refused(): void
    {
        foreach ([['logo_zoom' => 0.1], ['logo_zoom' => 99], ['logo_x' => 4]] as $onzin) {
            $this->actingAs($this->beheerder())
                ->post(route('website.ervaring.store'), $this->invoer([
                    'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
                    ...$onzin,
                ]))
                ->assertSessionHasErrors(array_key_first($onzin));
        }

        $this->assertSame(0, Experience::query()->count());
    }

    public function test_the_default_crop_shows_the_whole_image(): void
    {
        /*
         * Zonder uitsnede valt het terug op "het hele beeld passend,
         * gecentreerd, op wit" -- dezelfde stand waarmee de kiezer in het
         * formulier begint. Bij een liggend beeld blijft er dan boven en
         * onder wit over.
         */
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'logo' => UploadedFile::fake()->image('logo.png', 400, 100),
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            0,
            $this->hoekkleur((string) Experience::query()->sole()->logo_path)['alpha'],
        );
    }

    public function test_the_name_the_customer_chose_is_not_used(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'logo' => UploadedFile::fake()->image('../../.env.png', 200, 200),
            ]));

        $pad = (string) Experience::query()->sole()->logo_path;

        $this->assertStringStartsWith(Experience::MAP.'/', $pad);
        $this->assertStringNotContainsString('..', $pad);
        $this->assertStringNotContainsString('.env', $pad);
    }

    public function test_the_website_gets_a_url_and_not_a_path(): void
    {
        /*
         * De database bewaart het pad; wat naar het scherm gaat is een
         * adres. Verhuizen de bestanden ooit naar een andere opslag, dan
         * verandert er één regel in config/filesystems.php en geen rij in
         * de database.
         */
        $ervaring = Experience::factory()->create([
            'logo_path' => Experience::MAP.'/voorbeeld.webp',
        ]);

        // Het adres gaat via `/storage`, de map die `storage:link` naar de
        // schijf wijst -- en is dus iets anders dan het opgeslagen pad.
        $this->assertStringContainsString(
            '/storage/'.Experience::MAP.'/voorbeeld.webp',
            (string) $ervaring->logo(),
        );
        $this->assertNotSame($ervaring->logo_path, $ervaring->logo());
    }

    public function test_something_that_is_not_an_image_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'logo' => UploadedFile::fake()->create('script.php', 10, 'image/png'),
            ]))
            ->assertSessionHasErrors('logo');

        $this->assertSame(0, Experience::query()->count());
    }

    public function test_a_file_that_is_too_big_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'logo' => UploadedFile::fake()
                    ->image('groot.png', 1000, 1000)
                    ->size(4096),
            ]))
            ->assertSessionHasErrors('logo');
    }

    public function test_an_absurdly_large_canvas_is_refused(): void
    {
        /*
         * GD zet een afbeelding uitgepakt in het geheugen. Zonder deze
         * grens is een plaatje met heel veel pixels -- op schijf klein,
         * uitgepakt enorm -- een manier om de server om te duwen.
         */
        $this->actingAs($this->beheerder())
            ->post(route('website.ervaring.store'), $this->invoer([
                'logo' => UploadedFile::fake()->image('breed.png', 6000, 10),
            ]))
            ->assertSessionHasErrors('logo');
    }

    public function test_saving_without_a_file_keeps_the_logo(): void
    {
        // De val: wie een typefout verbetert, stuurt geen bestand mee.
        $ervaring = Experience::factory()->create([
            'logo_path' => Experience::MAP.'/blijft-staan.webp',
        ]);

        $this->actingAs($this->beheerder())
            ->put(route('website.ervaring.update', $ervaring), $this->invoer([
                'role_nl' => 'Iets anders',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            Experience::MAP.'/blijft-staan.webp',
            $ervaring->fresh()?->logo_path,
        );
    }

    public function test_asking_for_it_removes_the_logo(): void
    {
        $ervaring = Experience::factory()->create();

        Storage::disk(Experience::SCHIJF)->put(
            Experience::MAP.'/weg.webp',
            'niet echt een plaatje',
        );

        $ervaring->update(['logo_path' => Experience::MAP.'/weg.webp']);

        $this->actingAs($this->beheerder())
            ->put(route('website.ervaring.update', $ervaring), $this->invoer([
                'logo_verwijderen' => true,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertNull($ervaring->fresh()?->logo_path);
        Storage::disk(Experience::SCHIJF)->assertMissing(Experience::MAP.'/weg.webp');
    }

    public function test_replacing_a_logo_throws_the_old_one_away(): void
    {
        $ervaring = Experience::factory()->create();

        Storage::disk(Experience::SCHIJF)->put(
            Experience::MAP.'/oud.webp',
            'niet echt een plaatje',
        );

        $ervaring->update(['logo_path' => Experience::MAP.'/oud.webp']);

        $this->actingAs($this->beheerder())
            ->put(route('website.ervaring.update', $ervaring), $this->invoer([
                'logo' => UploadedFile::fake()->image('nieuw.png', 300, 300),
            ]))
            ->assertSessionHasNoErrors();

        Storage::disk(Experience::SCHIJF)->assertMissing(Experience::MAP.'/oud.webp');
        Storage::disk(Experience::SCHIJF)->assertExists(
            (string) $ervaring->fresh()?->logo_path,
        );
    }

    public function test_deleting_the_experience_takes_the_file_with_it(): void
    {
        /*
         * Zonder deze opruiming blijft elk bestand staan van elke ervaring
         * die ooit is weggegooid. Dat merk je niet -- de site werkt gewoon
         * -- tot de schijf vol is en niemand meer weet welke bestanden nog
         * ergens bij horen.
         */
        $ervaring = Experience::factory()->create();

        Storage::disk(Experience::SCHIJF)->put(
            Experience::MAP.'/meegaan.webp',
            'niet echt een plaatje',
        );

        $ervaring->update(['logo_path' => Experience::MAP.'/meegaan.webp']);

        $this->actingAs($this->beheerder())
            ->delete(route('website.ervaring.destroy', $ervaring));

        Storage::disk(Experience::SCHIJF)->assertMissing(Experience::MAP.'/meegaan.webp');
    }

    public function test_the_logo_reaches_the_public_timeline(): void
    {
        Experience::factory()->create([
            'logo_path' => Experience::MAP.'/op-de-site.webp',
        ]);

        $props = $this->get(route('home'))->viewData('page')['props'];

        $this->assertStringContainsString(
            'op-de-site.webp',
            (string) $props['experiences'][0]['logo'],
        );
    }
}
