<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De taalkeuze.
 *
 * Het ontwerp staat of valt met één regel: er is precies één plek die
 * beslist welke taal het wordt, en die leest twee bronnen in een vaste
 * volgorde. Elke knop vult alleen die bronnen.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_default_language_is_dutch(): void
    {
        $this->get(route('home'))->assertOk();

        $this->assertSame('nl', app()->getLocale());
    }

    public function test_a_visitor_can_switch_without_an_account(): void
    {
        $this->post(route('locale.switch', 'en'))->assertRedirect();

        $this->assertSame('en', session('locale'));

        $this->get(route('home'))->assertOk();
        $this->assertSame('en', app()->getLocale());
    }

    public function test_an_unknown_language_is_refused(): void
    {
        // De witte lijst is de enige bescherming: de taal komt uit de URL.
        $this->post(route('locale.switch', 'de'))->assertNotFound();

        $this->assertNull(session('locale'));
    }

    public function test_switching_while_logged_in_also_saves_it_on_the_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('locale.switch', 'en'));

        $this->assertSame('en', $user->fresh()?->locale);
    }

    public function test_the_account_wins_from_the_session(): void
    {
        $user = User::factory()->create(['locale' => 'nl']);

        // Anders zou een sessie op een gedeelde computer de voorkeur van de
        // eigenaar overschrijven.
        $this->actingAs($user)
            ->withSession(['locale' => 'en'])
            ->get(route('dashboard'));

        $this->assertSame('nl', app()->getLocale());
    }

    public function test_a_language_that_was_removed_from_the_list_is_ignored(): void
    {
        $user = User::factory()->create(['locale' => 'de']);

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame('nl', app()->getLocale());
    }

    public function test_the_current_language_is_shared_with_the_frontend(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get(route('home'))
            ->assertInertia(fn ($page) => $page
                ->where('locale', 'en')
                ->where('locales.nl', 'Nederlands')
                ->where('locales.en', 'English'));
    }

    /**
     * De vlaggetjes staan in public/ en gaan dus niet door Vite heen. Een
     * ontbrekend bestand levert geen bouwfout op maar een gebroken plaatje
     * in de knop, en dat zie je alleen als je er toevallig langs komt.
     *
     * De tabel hieronder is met opzet een kopie van die in LocaleFlag.vue:
     * komt er een taal bij zonder vlag, dan valt deze test om.
     */
    public function test_every_language_has_a_flag_file(): void
    {
        $vlaggen = [
            'nl' => 'nl.svg',
            'en' => 'gb.svg',
        ];

        foreach (array_keys(config('app.available_locales')) as $taal) {
            $this->assertArrayHasKey(
                $taal,
                $vlaggen,
                "Taal '{$taal}' heeft geen vlag in LocaleFlag.vue.",
            );

            $this->assertFileExists(public_path('flags/'.$vlaggen[$taal]));
        }
    }
}
