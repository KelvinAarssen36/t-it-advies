<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het laadscherm van het portaal terug naar de website.
 *
 * De spiegel van [`PortalEntryTest`](PortalEntryTest.php), maar met één
 * belangrijk verschil: hier is geen bestemming uit de sessie en dus ook geen
 * open omleiding mogelijk. Dat verschil is de moeite van het bewaken waard,
 * want "het gaat toch altijd naar de homepage" is precies het soort aanname
 * dat iemand later omzet in "we halen hem even uit de URL".
 */
class SiteEntryTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        // Deze route bestaat alleen voor wie in het portaal zit. Een
        // bezoeker gaat gewoon naar de homepage.
        $this->get(route('site.enter'))->assertRedirect(route('login'));
    }

    public function test_it_shows_the_loading_screen(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('site.enter'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('portal/SiteEntry')
                ->where('destination', route('home', absolute: false))
                ->has('duration'));
    }

    public function test_the_destination_cannot_be_steered(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['url.intended' => 'https://kwaadaardig.example/phishing'])
            ->get(route('site.enter', ['destination' => 'https://kwaadaardig.example']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('destination', route('home', absolute: false)));
    }
}
