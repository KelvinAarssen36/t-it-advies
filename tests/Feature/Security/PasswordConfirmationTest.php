<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het slotje naast "Beveiliging" in de instellingen.
 *
 * Achter die pagina zit een wachtwoordbevestiging. Het slotje vertelt vooraf
 * of je daar nog langs moet, zodat je niet verrast wordt door een
 * inlogscherm na een klik op een menu-item.
 *
 * Let op wat deze tests **niet** bewaken: dat de pagina dicht zit. Dat doet
 * de middleware `password.confirm`, en daar gaat
 * [`SecurityTest`](../Settings/SecurityTest.php) over. Hier gaat het om de
 * waarde waarop de weergave wordt gebaseerd.
 */
class PasswordConfirmationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_fresh_session_has_no_confirmation(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.passwordConfirmed', false));
    }

    public function test_logging_in_counts_as_confirming(): void
    {
        /*
         * Zonder dit vraagt de beveiligingspagina opnieuw om je wachtwoord,
         * ook als je dertig seconden eerder hebt ingelogd. Dat voelt niet
         * als zorgvuldigheid maar als een fout, en het leert iemand zijn
         * wachtwoord klakkeloos in te tikken zodra het gevraagd wordt.
         *
         * Zie de LoginResponse in FortifyServiceProvider voor de afweging.
         */
        $user = User::factory()->create();

        $this->post(route('login'), [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.passwordConfirmed', true));

        // En de pagina erachter gaat open zonder een tweede vraag.
        $this->get(route('security.edit'))->assertOk();
    }

    public function test_confirming_the_password_opens_the_lock(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'password']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.passwordConfirmed', true));
    }

    public function test_an_expired_confirmation_closes_the_lock_again(): void
    {
        /*
         * Dezelfde som als de middleware maakt. Zou die hier ontbreken, dan
         * bleef het slotje open staan terwijl je wél opnieuw je wachtwoord
         * moet invullen -- en dat is precies de verrassing die we willen
         * voorkomen.
         */
        $verlopen = time() - (int) config('auth.password_timeout') - 1;

        $this->actingAs(User::factory()->create())
            ->withSession(['auth.password_confirmed_at' => $verlopen])
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.passwordConfirmed', false));
    }

    public function test_a_visitor_never_gets_an_open_lock(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.passwordConfirmed', false));
    }

    public function test_the_confirmation_screen_offers_a_way_back(): void
    {
        // Zonder die uitweg is dit scherm een doodlopende straat: je komt er
        // ongevraagd terecht en uitloggen zou de enige weg terug zijn.
        $this->actingAs(User::factory()->create())
            ->get(route('password.confirm'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('auth/ConfirmPassword'));

        $this->assertStringContainsString(
            'profiel()',
            (string) file_get_contents(
                resource_path('js/pages/auth/ConfirmPassword.vue'),
            ),
        );
    }
}
