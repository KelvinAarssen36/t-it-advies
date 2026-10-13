<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De publieke site.
 *
 * Het uitgangspunt: een bezoeker hoort niets te zien dat naar het portaal
 * wijst. Er is één gebruiker -- de eigenaar -- en die kent zijn eigen adres.
 * Een knop naar het beheergedeelte wijst bezoekers alleen maar op een deur
 * die niet voor hen is.
 *
 * De kop toont die link op basis van `auth.user` uit de gedeelde props. Deze
 * tests bewaken dus de waarde waarop dat besluit wordt genomen.
 */
class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_visitor_gets_no_user_in_the_shared_props(): void
    {
        $this->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Welcome')
                ->where('auth.user', null)
                ->where('auth.permissions', [])
                ->where('auth.twoFactor', false));
    }

    public function test_the_owner_does_get_a_user_in_the_shared_props(): void
    {
        // De andere kant op: was dit leeg, dan zou de link nooit verschijnen
        // en zou de test hierboven om de verkeerde reden slagen.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.user.id', $user->id));
    }

    public function test_logging_out_lands_on_the_public_site(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('logout'))
            ->assertRedirect(route('home'));

        $this->assertGuest();
    }

    public function test_logging_out_clears_the_browser_history(): void
    {
        /*
         * Zonder dit blijven de pagina's die je als ingelogde gebruiker hebt
         * bekeken in de geschiedenis staan, inclusief hun props. Eén keer op
         * de terugknop zou de kop dan weer mét portaallink tonen.
         */
        $this->actingAs(User::factory()->create())
            ->post(route('logout'));

        $this->assertTrue(session(SessionKey::CLEAR_HISTORY));
    }
}
