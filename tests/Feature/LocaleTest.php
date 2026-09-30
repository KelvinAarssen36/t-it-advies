<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De taalkeuze.
 *
 * Het ontwerp staat of valt met één regel: er is precies één plek die
 * beslist welke taal het wordt, en die leest zijn bronnen in een vaste
 * volgorde. Elke knop vult alleen die bronnen.
 *
 * Die volgorde is: het profiel, dan de sessie, dan wat de browser vraagt,
 * dan de instelling uit config. De tests hieronder lopen hem van boven
 * naar beneden na, want het gaat niet om welke taal eruit komt maar om
 * welke bron er wint.
 */
class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_dutch_browser_gets_dutch(): void
    {
        $this->withHeader('Accept-Language', 'nl-NL,nl;q=0.9,en;q=0.8')
            ->get(route('home'))
            ->assertOk();

        $this->assertSame('nl', app()->getLocale());
    }

    public function test_a_flemish_browser_also_gets_dutch(): void
    {
        // Symfony kijkt naar het taaldeel en niet naar het land, en dat
        // moet zo blijven: nl-BE is net zo goed Nederlands.
        $this->withHeader('Accept-Language', 'nl-BE')->get(route('home'));

        $this->assertSame('nl', app()->getLocale());
    }

    public function test_any_other_browser_gets_english(): void
    {
        $this->withHeader('Accept-Language', 'de-DE,de;q=0.9')
            ->get(route('home'));

        $this->assertSame('en', app()->getLocale());
    }

    public function test_a_browser_that_says_nothing_gets_english(): void
    {
        // Geen voorkeur is geen Nederlands. Dat is de veilige kant: een
        // bezoeker die geen Nederlands leest ziet meteen iets dat hij
        // begrijpt, en de knop staat er voor het andere geval.
        //
        // `flushHeaders` haalt de Nederlandse kop weg die TestCase voor
        // elke test meestuurt; deze test gaat juist over het ontbreken
        // ervan.
        $this->flushHeaders();

        $this->get(route('home'))->assertOk();

        $this->assertSame('en', app()->getLocale());
    }

    public function test_the_choice_of_the_visitor_beats_the_browser(): void
    {
        $this->withSession(['locale' => 'nl'])
            ->withHeader('Accept-Language', 'de-DE')
            ->get(route('home'));

        $this->assertSame('nl', app()->getLocale());
    }

    public function test_the_answer_says_it_depends_on_the_language(): void
    {
        /*
         * Zonder deze kop kan een cache ertussen de Nederlandse pagina
         * teruggeven aan een Engelse bezoeker. Dat is het soort fout dat
         * je zelf nooit ziet, want jouw browser vraagt altijd hetzelfde.
         */
        $response = $this->get(route('home'));

        /*
         * Niet `assertHeader`: Inertia zet er zelf al `X-Inertia` in, en
         * die vergelijking kijkt alleen naar de eerste waarde. Een
         * `Vary` mag er meer dan één hebben, en de onze hoort erbij te
         * komen in plaats van die van Inertia weg te duwen.
         */
        $this->assertContains(
            'Accept-Language',
            $response->headers->all('Vary'),
        );
        $this->assertContains('X-Inertia', $response->headers->all('Vary'));
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

        // Mét een Nederlandse browser, zodat deze test over het negeren
        // van 'de' gaat en niet over wat de terugval toevallig oplevert.
        $this->actingAs($user)
            ->withHeader('Accept-Language', 'nl-NL')
            ->get(route('dashboard'));

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
