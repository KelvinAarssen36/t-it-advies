<?php

namespace Tests\Feature\Security;

use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use App\Http\Responses\PasskeyLoginResponse;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use PragmaRX\Google2FA\Google2FA;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * De extra stap na een passkey.
 *
 * Een passkey is één handeling: je vinger, en je bent binnen. Bij een
 * wachtwoordlogin komt daarna altijd nog de authenticator; bij een passkey
 * niet. Wie dat verschil niet wil, zet deze schakelaar aan.
 *
 * Drie dingen moeten kloppen:
 *
 * - de schakelaar staat achter een verse authenticator-code, allebei de
 *   kanten op;
 * - staat hij aan, dan is de eigenaar ná de passkey nog **niet** ingelogd;
 * - staat hij uit, dan verandert er niets aan de bestaande stroom.
 *
 * Zie docs/security/extra-stap-na-een-passkey.md.
 */
class PasskeyExtraStapTest extends TestCase
{
    use RefreshDatabase;

    private string $secret = '';

    private function eigenaar(bool $extraStap = false): User
    {
        $this->secret = (new Google2FA)->generateSecretKey();

        $user = User::factory()->create();

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($this->secret),
            'two_factor_confirmed_at' => now(),
            'passkey_requires_two_factor' => $extraStap,
        ])->save();

        return $user;
    }

    /* --- De schakelaar ----------------------------------------------------- */

    /** De code zoals hij nu op de telefoon van de eigenaar staat. */
    private function huidigeCode(): string
    {
        return (new Google2FA)->getCurrentOtp($this->secret);
    }

    public function test_a_guest_cannot_touch_the_switch(): void
    {
        $this->put(route('security.passkey-stap'), ['aan' => true, 'code' => '123456'])
            ->assertRedirect(route('login'));
    }

    /**
     * Zonder code gebeurt er niets.
     *
     * **De code zit in het verzoek en niet in de middleware**, anders dan
     * bij de meeste gevoelige acties. `2fa.confirm` kan een PUT namelijk
     * niet onthouden: die stuurt je naar het codescherm en gooit je
     * verzoek weg, zodat het schuifje terugspringt en je het nog een keer
     * moet omzetten. Zie App\Support\Security\Authenticator.
     */
    public function test_turning_it_on_needs_a_code(): void
    {
        $eigenaar = $this->eigenaar();

        $this->actingAs($eigenaar)
            ->put(route('security.passkey-stap'), ['aan' => true])
            ->assertSessionHasErrors('code');

        $this->assertFalse((bool) $eigenaar->refresh()->passkey_requires_two_factor);
    }

    public function test_a_wrong_code_does_not_move_the_switch(): void
    {
        $eigenaar = $this->eigenaar();

        $this->actingAs($eigenaar)
            ->put(route('security.passkey-stap'), ['aan' => true, 'code' => '000000'])
            ->assertSessionHasErrors('code');

        $this->assertFalse((bool) $eigenaar->refresh()->passkey_requires_two_factor);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::SensitiveActionFailed->value,
            'outcome' => SecurityOutcome::Failure->value,
        ]);
    }

    /** Een recovery code telt hier niet; je bent al ingelogd. */
    public function test_a_recovery_code_is_refused(): void
    {
        $eigenaar = $this->eigenaar();

        $this->actingAs($eigenaar)
            ->put(route('security.passkey-stap'), [
                'aan' => true,
                'code' => 'abcdefghij-klmnopqrst',
            ])
            ->assertSessionHasErrors('code');

        $this->assertFalse((bool) $eigenaar->refresh()->passkey_requires_two_factor);
    }

    /**
     * En uitzetten vraagt er net zo goed om.
     *
     * Dit is de belangrijkste van de twee. Zou alleen aanzetten een code
     * vragen, dan kan wie achter een open scherm gaat zitten de extra stap
     * er gewoon af halen en daarna rustig met de passkey naar binnen.
     */
    public function test_turning_it_off_needs_one_too(): void
    {
        $eigenaar = $this->eigenaar(extraStap: true);

        $this->actingAs($eigenaar)
            ->put(route('security.passkey-stap'), ['aan' => false])
            ->assertSessionHasErrors('code');

        $this->assertTrue((bool) $eigenaar->refresh()->passkey_requires_two_factor);
    }

    /**
     * Met de code erbij staat hij meteen goed.
     *
     * Dit is de test die het gemelde probleem afdekt: één verzoek, en de
     * stand is om. Geen omweg langs een ander scherm waarna je het nog een
     * keer moet doen.
     */
    public function test_one_request_with_the_code_is_enough(): void
    {
        $eigenaar = $this->eigenaar();

        $this->actingAs($eigenaar)
            ->put(route('security.passkey-stap'), [
                'aan' => true,
                'code' => $this->huidigeCode(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertTrue((bool) $eigenaar->refresh()->passkey_requires_two_factor);

        /*
         * Een minuut verder, want Fortify weigert een code die al een keer
         * is gebruikt -- en terecht: een TOTP-code is eenmalig. In het
         * echt kijkt de eigenaar gewoon op zijn telefoon, waar dan al een
         * nieuwe staat.
         */
        $this->travel(60)->seconds();

        $this->actingAs($eigenaar)
            ->put(route('security.passkey-stap'), [
                'aan' => false,
                'code' => $this->huidigeCode(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse((bool) $eigenaar->refresh()->passkey_requires_two_factor);
    }

    /**
     * En een geslaagde code telt daarna als verse bevestiging.
     *
     * Dezelfde uitkomst als na het aparte codescherm. Of je de code nu in
     * dit venster of daar invult, je hoort er even lang mee vooruit te
     * kunnen.
     */
    public function test_the_code_also_counts_as_a_fresh_confirmation(): void
    {
        $eigenaar = $this->eigenaar();

        $this->actingAs($eigenaar)->put(route('security.passkey-stap'), [
            'aan' => true,
            'code' => $this->huidigeCode(),
        ]);

        $this->assertIsInt(
            session((string) config('security.sensitive_actions.session_key')),
        );
    }

    public function test_both_directions_are_logged(): void
    {
        $eigenaar = $this->eigenaar();

        $this->actingAs($eigenaar)->put(route('security.passkey-stap'), [
            'aan' => true,
            'code' => $this->huidigeCode(),
        ]);

        // Zie de toelichting hierboven: dezelfde code werkt geen twee keer.
        $this->travel(60)->seconds();

        $this->actingAs($eigenaar)->put(route('security.passkey-stap'), [
            'aan' => false,
            'code' => $this->huidigeCode(),
        ]);

        $this->assertSame(
            2,
            SecurityEvent::query()
                ->where('event', SecurityEventType::PasskeyStepChanged->value)
                ->where('outcome', SecurityOutcome::Success->value)
                ->count(),
        );
    }

    /** De ingevoerde code belandt nergens in het logboek. */
    public function test_the_code_is_never_stored(): void
    {
        $eigenaar = $this->eigenaar();
        $code = $this->huidigeCode();

        $this->actingAs($eigenaar)->put(route('security.passkey-stap'), [
            'aan' => true,
            'code' => $code,
        ]);

        $alles = SecurityEvent::query()->get()
            ->map(fn (SecurityEvent $regel) => (string) json_encode($regel->context))
            ->implode(' ');

        $this->assertStringNotContainsString($code, $alles);
    }

    public function test_the_screen_knows_the_current_state(): void
    {
        $eigenaar = $this->eigenaar(extraStap: true);

        $this->actingAs($eigenaar)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.edit'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('passkeyStep', true));
    }

    /* --- Wat er na de passkey gebeurt --------------------------------------- */

    /**
     * De klasse die het pakket gebruikt is de onze.
     *
     * Zonder deze binding draait alles hieronder op de standaardklasse van
     * het pakket en doet de schakelaar helemaal niets -- zonder dat er
     * iets omvalt. Zie FortifyServiceProvider::register().
     */
    public function test_our_own_response_is_bound(): void
    {
        $this->assertInstanceOf(
            PasskeyLoginResponse::class,
            app(PasskeyLoginResponseContract::class),
        );
    }

    /** Met de schakelaar uit verandert er niets: hij blijft ingelogd. */
    public function test_without_the_switch_the_passkey_is_enough(): void
    {
        $eigenaar = $this->eigenaar();

        $antwoord = $this->alsNaEenPasskey($eigenaar);

        $this->assertTrue(Auth::check());
        $this->assertNull(session('login.id'));
        $this->assertStringNotContainsString(
            route('two-factor.login'),
            (string) $antwoord->headers->get('Location'),
        );
    }

    /**
     * En hij krijgt hetzelfde onthaal als na een wachtwoord.
     *
     * Dit ging mis: het pakket stuurt je naar `passkeys.redirect` -- dat
     * staat standaard op `/`, de publieke website -- en zet niets in de
     * sessie. Daardoor miste een passkey-login de begroeting, het
     * laadscherm en de onthouden bestemming.
     */
    public function test_a_passkey_login_gets_the_same_welcome(): void
    {
        $eigenaar = $this->eigenaar();

        $antwoord = $this->alsNaEenPasskey($eigenaar);

        $this->assertSame(
            route('portal.enter'),
            $antwoord->headers->get('Location'),
        );

        $this->assertTrue(session('portal.welcome'));
    }

    /**
     * De onthouden bestemming blijft staan voor het laadscherm.
     *
     * `redirect()->intended()` zou hem opmaken, en dan komt de eigenaar
     * op het dashboard in plaats van waar hij heen wilde.
     */
    public function test_the_intended_destination_survives(): void
    {
        $eigenaar = $this->eigenaar();

        session()->put('url.intended', route('safety.show'));

        $this->alsNaEenPasskey($eigenaar);

        $this->assertSame(route('safety.show'), session('url.intended'));
    }

    /**
     * Een passkey telt niet als wachtwoordbevestiging.
     *
     * Hij bewijst dat je het apparaat hebt, niet dat je het wachtwoord
     * kent -- en dat laatste is precies wat `RequirePassword` wil weten.
     */
    public function test_a_passkey_is_not_a_password_confirmation(): void
    {
        $eigenaar = $this->eigenaar();

        $this->alsNaEenPasskey($eigenaar);

        $this->assertNull(session('auth.password_confirmed_at'));
    }

    /**
     * Ook niet met de extra stap, al loopt die langs Fortify.
     *
     * Die stroom eindigt in de challenge van Fortify, en daar wordt de
     * bevestiging normaal wél gezet. Zonder de uitzondering zou de
     * strengere instelling juist het zwakkere resultaat geven.
     */
    public function test_nor_after_the_extra_step(): void
    {
        $eigenaar = $this->eigenaar(extraStap: true);

        $this->alsNaEenPasskey($eigenaar);

        $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp($this->secret),
        ]);

        $this->assertTrue(Auth::check());
        $this->assertNull(session('auth.password_confirmed_at'));
        $this->assertTrue(session('portal.welcome'));
    }

    /**
     * Met de schakelaar aan is hij ná de passkey nog niet binnen.
     *
     * Dit is de kern. Niet "hij ziet een scherm", maar: `Auth::check()` is
     * onwaar. Zou hij wel ingelogd zijn en alleen met middleware worden
     * tegengehouden, dan is elke route die je vergeet een gat.
     */
    public function test_with_the_switch_on_he_is_logged_out_again(): void
    {
        $eigenaar = $this->eigenaar(extraStap: true);

        $antwoord = $this->alsNaEenPasskey($eigenaar);

        $this->assertFalse(Auth::check());

        $this->assertSame($eigenaar->id, session('login.id'));
        $this->assertTrue(session('login.via_passkey'));

        $this->assertSame(
            route('two-factor.login'),
            $antwoord->headers->get('Location'),
        );

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::PasskeyStepChallenged->value,
            'outcome' => SecurityOutcome::Success->value,
            'user_id' => $eigenaar->id,
        ]);
    }

    /**
     * En met die code erbij komt hij alsnog binnen.
     *
     * De hele keten, want dat is wat telt: de schakelaar mag de eigenaar
     * niet buitensluiten.
     */
    public function test_and_the_code_finishes_the_login(): void
    {
        $eigenaar = $this->eigenaar(extraStap: true);

        $this->alsNaEenPasskey($eigenaar);

        $this->post(route('two-factor.login.store'), [
            'code' => (new Google2FA)->getCurrentOtp($this->secret),
        ])->assertRedirect();

        $this->assertTrue(Auth::check());
        $this->assertSame($eigenaar->id, Auth::id());
    }

    public function test_a_wrong_code_does_not_let_him_in(): void
    {
        $eigenaar = $this->eigenaar(extraStap: true);

        $this->alsNaEenPasskey($eigenaar);

        $this->post(route('two-factor.login.store'), ['code' => '000000']);

        $this->assertFalse(Auth::check());
    }

    /**
     * Zonder bevestigde 2FA doet de schakelaar niets.
     *
     * Hij zou anders een code beloven die niet bestaat, en dan staat de
     * eigenaar voor een scherm waar hij niets kan invullen. Buitengesloten
     * door een beveiliging die hij zelf aanzette.
     */
    public function test_without_confirmed_two_factor_the_switch_is_ignored(): void
    {
        $eigenaar = User::factory()->create();
        $eigenaar->forceFill([
            'passkey_requires_two_factor' => true,
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->alsNaEenPasskey($eigenaar);

        $this->assertTrue(Auth::check());
    }

    /**
     * Doen alsof het pakket zojuist een passkey heeft goedgekeurd.
     *
     * Het pakket logt in en geeft daarna het antwoord terug dat in de
     * container staat; dat laatste stuk is van ons en is wat we hier
     * toetsen. De WebAuthn-handtekening zelf namaken zou dit een test van
     * het pakket maken in plaats van van onze keuze.
     */
    private function alsNaEenPasskey(User $eigenaar): Response
    {
        $this->actingAs($eigenaar);

        // Een verzoek met een echte sessie eronder; `toResponse()` schrijft
        // erin en Auth leest eruit.
        $request = Request::create(route('passkey.login'), 'POST');
        $request->setLaravelSession(session()->driver());

        return app(PasskeyLoginResponseContract::class)->toResponse($request);
    }
}
