<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * De QR-code voor het instellen van tweestapsverificatie.
 *
 * Twee eigenschappen bepalen of een scanner hem leest, en allebei zijn ze
 * ooit misgegaan:
 *
 * - **De stille zone.** Fortify genereert met marge 0, en dan raakt de code
 *   de rand. De camera-app van een telefoon trekt dat nog wel, de
 *   eenvoudiger scanner in een authenticator-app niet.
 * - **De kleurrichting.** Donkere modules op een lichte achtergrond, nooit
 *   omgekeerd.
 *
 * Deze test kijkt naar de SVG zelf. De weergave in de browser mag er niets
 * meer aan veranderen -- geen invert-filter, geen donkere achtergrond
 * eronder.
 */
class TwoFactorQrCodeTest extends TestCase
{
    use RefreshDatabase;

    private function userWithSecret(): User
    {
        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(
                app(Google2FA::class)->generateSecretKey()
            ),
        ])->save();

        return $user;
    }

    public function test_the_code_has_a_quiet_zone(): void
    {
        $svg = $this->userWithSecret()->twoFactorQrCodeSvg();

        // Vier modules rondom, zoals de QR-standaard voorschrijft. Zonder
        // de eigen versie in het User-model staat hier translate(0,0).
        $this->assertStringContainsString('translate(4,4)', $svg);
    }

    public function test_the_code_is_dark_on_light_and_not_the_other_way_round(): void
    {
        $svg = $this->userWithSecret()->twoFactorQrCodeSvg();

        // Een wit achtergrondvlak over de volle breedte...
        $this->assertMatchesRegularExpression(
            '/<rect[^>]+fill="#ffffff"/i',
            $svg,
        );

        // ...en modules in Midnight Navy eroverheen.
        $this->assertStringContainsString('#061626', mb_strtolower($svg));
    }
}
