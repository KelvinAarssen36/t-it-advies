<?php

namespace Tests\Feature\Website;

use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as GeroutedeRoute;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Elke kop-route komt ergens uit.
 *
 * **Deze test bestaat door een fout die een tijd heeft stilgestaan.**
 * `routes/website.php` bond `website.werkwijze.kop` aan
 * `WorkStepController::kop`, en die methode bestond niet. De route stond
 * in de lijst, de knop stond op het scherm, het venster stuurde ernaartoe
 * -- en op opslaan kreeg de eigenaar een 500. Elke andere module had de
 * methode wel; dit was de enige die hem miste, én de enige zonder test op
 * die route.
 *
 * Wat hier wordt bewaakt is dus niet "de kop wordt goed opgeslagen" --
 * daar zijn ServiceHeadingTest en ExperienceHeadingTest voor -- maar het
 * grovere geval eronder: **wijst elke kop-route naar iets dat bestaat?**
 * Dat is precies het soort fout dat geen enkele moduletest vangt, omdat
 * elke moduletest alleen zijn eigen module kent.
 *
 * Komt er een module bij, dan loopt deze test hem vanzelf mee.
 */
class KoptekstRoutesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * Alle routes die een sectiekop opslaan.
     *
     * Op de naam en niet op een lijst die we hier bijhouden: een lijst in
     * een test is een tweede plek om een module te vergeten, en dat is nu
     * juist de fout die deze test moet vangen.
     *
     * @return array<string, GeroutedeRoute>
     */
    private function kopRoutes(): array
    {
        $gevonden = [];

        foreach (Route::getRoutes() as $route) {
            $naam = $route->getName();

            if (is_string($naam) && str_starts_with($naam, 'website.') && str_ends_with($naam, '.kop')) {
                $gevonden[$naam] = $route;
            }
        }

        return $gevonden;
    }

    public function test_every_heading_route_points_at_a_method_that_exists(): void
    {
        $routes = $this->kopRoutes();

        $this->assertNotEmpty(
            $routes,
            'Er is geen enkele kop-route gevonden; de zoekterm klopt niet meer.',
        );

        $ontbreekt = [];

        foreach ($routes as $naam => $route) {
            $actie = $route->getActionName();

            // "App\Http\Controllers\...@kop" uit elkaar halen.
            if (! str_contains($actie, '@')) {
                continue;
            }

            [$klasse, $methode] = explode('@', $actie, 2);

            if (! method_exists($klasse, $methode)) {
                $ontbreekt[] = $naam.' -> '.$actie;
            }
        }

        $this->assertSame(
            [],
            $ontbreekt,
            'Deze kop-routes wijzen naar een methode die niet bestaat, en geven dus een 500 '
                .'zodra de eigenaar op opslaan drukt: '.implode(', ', $ontbreekt),
        );
    }

    /**
     * En ze geven ook echt geen serverfout.
     *
     * Met een lege lading, dus een 302 (opgeslagen of niets veranderd) of
     * een 422 (validatie) zijn allebei goed. Het enige dat hier niet mag
     * is een 5xx: dat betekent dat er iets stuk is in plaats van streng.
     */
    public function test_no_heading_route_returns_a_server_error(): void
    {
        $beheerder = $this->beheerder();
        $stuk = [];

        foreach ($this->kopRoutes() as $naam => $route) {
            $antwoord = $this->actingAs($beheerder)->put(route($naam), [
                'eyebrow_nl' => 'Opschrift',
                'title_nl' => 'Een titel',
                'intro_nl' => 'Een zin eronder.',
            ]);

            if ($antwoord->getStatusCode() >= 500) {
                $stuk[] = $naam.' gaf '.$antwoord->getStatusCode();
            }
        }

        $this->assertSame([], $stuk, implode(', ', $stuk));
    }
}
