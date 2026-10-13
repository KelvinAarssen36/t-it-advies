<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het rondje portaal → website → portaal.
 *
 * Sinds er een knop naar de website in de zijbalk staat, maakt de eigenaar
 * dat rondje meerdere keren per dag. Er mag dus niets onderweg kwijtraken:
 * je blijft ingelogd, en een wachtwoord dat je al hebt bevestigd hoef je
 * niet opnieuw in te voeren.
 *
 * Dat klinkt vanzelfsprekend, maar het hangt aan de sessie -- en die is
 * precies wat je bij een uitstapje naar een publieke pagina kwijt zou
 * kunnen raken.
 */
class RoundTripTest extends TestCase
{
    use RefreshDatabase;

    public function test_you_stay_logged_in_on_the_way_there_and_back(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('site.enter'))->assertOk();
        $this->get(route('home'))->assertOk();
        $this->get(route('portal.enter'))->assertOk();

        $this->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_a_confirmed_password_survives_the_trip(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('password.confirm.store'), ['password' => 'password']);

        // Het rondje: naar de website en weer terug.
        $this->get(route('site.enter'));
        $this->get(route('home'));
        $this->get(route('portal.enter'));

        // Het slotje staat nog open...
        $this->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('auth.passwordConfirmed', true));

        // ...en de pagina erachter vraagt niet opnieuw.
        $this->get(route('security.edit'))->assertOk();
    }
}
