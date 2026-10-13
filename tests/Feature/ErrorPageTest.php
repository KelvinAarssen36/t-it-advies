<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De foutpagina van het portaal.
 *
 * Hij staat in de schil van het portaal, met de zijbalk eromheen: een fout
 * is geen reden om iemand uit zijn omgeving te gooien.
 *
 * Een bezoeker op de landing krijgt voorlopig de standaardpagina van
 * Laravel. Die kant krijgt later een eigen variant, en die hoort er anders
 * uit te zien.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_logged_in_user_gets_the_portal_error_page(): void
    {
        /*
         * `has('auth.user')` is de belangrijkste regel van dit bestand.
         *
         * Die prop wordt gedeeld door HandleInertiaRequests, en die
         * middleware zit in de groep `web`. Staat hij er, dan heeft die
         * groep gedraaid en krijgt de pagina dus de zijbalk en het
         * accountmenu. Staat hij er niet, dan is de foutpagina buiten de
         * middleware om gerenderd -- en dan is er in de browser ook geen
         * sessie, waardoor een ingelogde beheerder gewoon de standaard
         * 404 van Laravel te zien krijgt. Precies dat ging hier een keer
         * mis. Zie FallbackController.
         */
        $this->actingAs(User::factory()->create())
            ->get('/bestaat-echt-niet')
            ->assertNotFound()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 404)
                ->has('auth.user'));
    }

    public function test_a_forbidden_page_also_gets_the_portal_shell(): void
    {
        // Deze komt uit een route en dus wél langs de middleware; hij loopt
        // via de respond-callback in bootstrap/app.php in plaats van via de
        // fallback. Allebei de wegen moeten dezelfde pagina opleveren.
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Error')
                ->where('status', 403)
                ->has('auth.user'));
    }

    public function test_the_status_code_is_kept(): void
    {
        // Een nette pagina met een 200 erachter is voor een zoekmachine en
        // voor een monitor een bestaande pagina. De code moet kloppen.
        $this->actingAs(User::factory()->create())
            ->get('/bestaat-echt-niet')
            ->assertStatus(404);
    }

    public function test_a_visitor_keeps_the_default_page(): void
    {
        $this->get('/bestaat-echt-niet')
            ->assertNotFound()
            ->assertDontSee('data-page', false);
    }

    public function test_an_api_request_still_gets_json(): void
    {
        $this->actingAs(User::factory()->create())
            ->getJson('/bestaat-echt-niet')
            ->assertNotFound()
            ->assertJsonStructure(['message']);
    }
}
