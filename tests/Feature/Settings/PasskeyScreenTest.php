<?php

namespace Tests\Feature\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het passkeygedeelte op het scherm Beveiliging.
 *
 * **Wat hier getest wordt is de bedrading en niet de WebAuthn-ceremonie
 * zelf.** Die ceremonie vraagt een echte authenticator -- een vingerafdruk,
 * een gezicht, een beveiligingssleutel -- en is in een testsuite niet na te
 * bootsen. Dat deel komt uit `laravel/passkeys` en wordt daar getest.
 *
 * Wat er hier omheen zit is wél van ons, en juist daar ging het mis:
 *
 * - De knoptekst op het inlogscherm stond in het **Engels**. `PasskeyVerify`
 *   had Engelse terugvalteksten, het scherm Even je wachtwoord gaf eigen
 *   teksten mee en het inlogscherm niet. Daar stond dus "Sign in with a
 *   passkey", ook met het portaal in het Nederlands.
 * - Het verwijderen ging via een **eigen venster** in plaats van het
 *   gedeelde bevestigingsvenster van dit project.
 *
 * Geen van beide was te zien in een gewone test, want het zijn teksten en
 * componenten en geen gedrag. Daarom leest een deel van deze tests de
 * bronbestanden, net als `TranslationsTest`.
 */
class PasskeyScreenTest extends TestCase
{
    use RefreshDatabase;

    private function bestand(string $pad): string
    {
        return (string) file_get_contents(resource_path("js/{$pad}"));
    }

    /**
     * Hetzelfde bestand, maar zonder commentaar.
     *
     * Nodig omdat het commentaar in deze componenten de oude, foute teksten
     * **noemt** om uit te leggen wat er misging. Zonder deze stap vindt een
     * test die zoekt naar "Sign in with a passkey" zijn eigen uitleg en
     * meldt hij een fout die er niet is.
     */
    private function zonderCommentaar(string $pad): string
    {
        $inhoud = $this->bestand($pad);

        $inhoud = (string) preg_replace('#/\*.*?\*/#s', '', $inhoud);
        $inhoud = (string) preg_replace('#<!--.*?-->#s', '', $inhoud);

        return (string) preg_replace('#^\s*//.*$#m', '', $inhoud);
    }

    /* --- De bedrading ----------------------------------------------------- */

    /**
     * Alle routes die het pakket nodig heeft, bestaan.
     *
     * Verdwijnt er één -- bijvoorbeeld doordat `Features::passkeys()` uit
     * de lijst in `config/fortify.php` valt -- dan verdwijnt de knop zonder
     * foutmelding, en dat is precies het soort stilte waar je niet achter
     * komt.
     */
    public function test_all_passkey_routes_are_registered(): void
    {
        $namen = collect(app('router')->getRoutes())
            ->map(fn ($route) => (string) $route->getName())
            ->filter(fn (string $naam) => str_starts_with($naam, 'passkey.'))
            ->values();

        foreach ([
            'passkey.store',
            'passkey.destroy',
            'passkey.registration-options',
            'passkey.login',
            'passkey.login-options',
            'passkey.confirm',
            'passkey.confirm-options',
        ] as $verwacht) {
            $this->assertContains($verwacht, $namen->all());
        }
    }

    /**
     * Het ontdekkingsdocument wijst naar het scherm Beveiliging.
     *
     * Wachtwoordmanagers lezen dit om een knop "beheer je passkeys" te
     * kunnen tonen. Het is met opzet openbaar: er staat niets persoonlijks
     * in, alleen waar je heen moet.
     */
    public function test_the_wellknown_document_points_at_the_security_screen(): void
    {
        $this->get('/.well-known/passkey-endpoints')
            ->assertOk()
            ->assertJson([
                'enroll' => route('security.edit'),
                'manage' => route('security.edit'),
            ]);
    }

    /** Een gebruiker begint zonder passkeys en de relatie werkt. */
    public function test_a_user_starts_without_passkeys(): void
    {
        $gebruiker = User::factory()->create();

        $this->assertCount(0, $gebruiker->passkeys()->get());
    }

    /* --- De teksten en de huisstijl --------------------------------------- */

    /**
     * Er staat geen Engelse terugvaltekst meer in `PasskeyVerify`.
     *
     * Dit is de test voor de fout zelf. `TranslationsTest` ving hem niet:
     * die zoekt naar Nederlandse zinnen die `$t()` omzeilen, en dit was
     * Engels in een stukje JavaScript.
     */
    public function test_the_passkey_button_has_no_english_fallback(): void
    {
        $component = $this->zonderCommentaar('components/PasskeyVerify.vue');

        foreach ([
            'Sign in with a passkey',
            'Authenticating...',
            'Or continue with email',
        ] as $engels) {
            $this->assertStringNotContainsString(
                $engels,
                $component,
                'De passkeyknop heeft weer een Engelse terugvaltekst.',
            );
        }
    }

    /**
     * Het inlogscherm krijgt zijn teksten uit de vertaling.
     *
     * Het scherm geeft ze niet zelf mee -- dat hoeft ook niet, de
     * standaardteksten zijn nu Nederlands -- maar dan moeten die
     * standaardteksten wel door `t()` gaan.
     */
    public function test_the_default_texts_go_through_the_translation(): void
    {
        $component = $this->zonderCommentaar('components/PasskeyVerify.vue');

        $this->assertStringContainsString("t('Inloggen met een passkey')", $component);
        $this->assertStringContainsString("t('Of log in met je e-mailadres')", $component);
    }

    /**
     * Een passkey verwijderen gaat via het gedeelde bevestigingsvenster.
     *
     * Elk ander verwijderen in dit portaal doet dat, en dit was de enige
     * plek met een eigen `Dialog`. Twee vensters voor dezelfde handeling
     * lopen vroeg of laat uit elkaar.
     */
    public function test_deleting_a_passkey_uses_the_shared_confirmation(): void
    {
        $component = $this->zonderCommentaar('components/PasskeyItem.vue');

        $this->assertStringContainsString('bevestigVerwijderen', $component);
        $this->assertStringNotContainsString('DialogTrigger', $component);
    }

    /**
     * De melding scheidt "geen https" van "browser kan het niet".
     *
     * De controle in de bibliotheek is `globalThis.PublicKeyCredential !==
     * undefined`, en browsers stellen dat object alleen beschikbaar op een
     * beveiligde verbinding. Op een `.test`-adres kreeg je daardoor te
     * horen dat je browser geen passkeys kende, terwijl die het prima kan.
     */
    public function test_the_unsupported_message_tells_the_two_causes_apart(): void
    {
        $component = $this->zonderCommentaar('components/PasskeyRegister.vue');

        $this->assertStringContainsString('isSecureContext', $component);
        $this->assertStringContainsString('beveiligde verbinding (https)', $component);
    }

    /* --- Wat er niet mag veranderen zonder gesprek ------------------------- */

    /**
     * De Permissions-Policy blokkeert WebAuthn niet.
     *
     * **Dit is de val die je pas in productie ziet.** WebAuthn hangt aan de
     * policy-onderdelen `publickey-credentials-create` en
     * `publickey-credentials-get`. Staan die niet in de kop, dan geldt hun
     * standaard en mag de eigen site ze gebruiken -- precies wat we willen.
     * Zet iemand ze er ooit in met een lege lijst, dan weigert de browser
     * elke passkey zonder dat er iets in een logboek belandt.
     */
    public function test_the_permissions_policy_does_not_block_webauthn(): void
    {
        $kop = (string) $this->get(route('login'))->headers->get('Permissions-Policy');

        $this->assertNotSame('', $kop, 'De Permissions-Policy-kop is verdwenen.');
        $this->assertStringNotContainsString('publickey-credentials', $kop);
    }
}
