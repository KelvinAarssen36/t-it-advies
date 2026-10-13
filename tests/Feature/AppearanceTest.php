<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Het thema van het portaal.
 *
 * Er zijn twee waarden, licht en donker, en donker is de standaard -- dat is
 * de huisstijl, en de publieke site staat er altijd in. "Systeem" uit de
 * starter kit is weggehaald.
 *
 * De server zet `dark` al op <html> op basis van een cookie, vóórdat er
 * JavaScript draait. Zonder dat zie je bij elke paginalading een flits van
 * het verkeerde thema, en dat is precies wat deze tests bewaken.
 */
class AppearanceTest extends TestCase
{
    use RefreshDatabase;

    private function bezoek(?string $cookie): TestResponse
    {
        $verzoek = $this->actingAs(User::factory()->create());

        if ($cookie !== null) {
            $verzoek = $verzoek->withUnencryptedCookie('appearance', $cookie);
        }

        return $verzoek->get(route('profile.edit'));
    }

    public function test_dark_is_the_default(): void
    {
        $this->bezoek(null)->assertSee('class="dark"', false);
    }

    public function test_light_is_respected(): void
    {
        $this->bezoek('light')->assertDontSee('class="dark"', false);
    }

    public function test_the_old_system_value_becomes_dark(): void
    {
        // Wie de vorige versie heeft gebruikt heeft 'system' in zijn cookie
        // staan. Dat mag geen leeg of half thema opleveren.
        $this->bezoek('system')->assertSee('class="dark"', false);
    }

    public function test_nonsense_becomes_dark(): void
    {
        // De waarde komt uit een cookie, en dat is iets wat een bezoeker
        // zelf zet. Alles wat niet letterlijk 'light' is, is donker.
        $this->bezoek('<script>')->assertSee('class="dark"', false);
    }
}
