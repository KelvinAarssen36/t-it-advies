<?php

namespace Tests\Feature\Settings;

use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use App\Mail\InlogadresAangevraagdMail;
use App\Mail\InlogadresBevestigenMail;
use App\Mail\InlogadresGewijzigdMail;
use App\Models\EmailChange;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Het inlogadres wijzigen.
 *
 * Dit is de gevoeligste CRUD van het portaal: er is één account, en als het
 * adres daarvan naar een postvak wijst dat niet bestaat, komt niemand er
 * meer in. Daarom toetst dit bestand niet alleen de gelukkige weg, maar
 * vooral de sloten en het vangnet:
 *
 * - drie sloten vóór de aanvraag (code, wachtwoord, bevestiging);
 * - het adres op `users` blijft staan tot de bevestiging;
 * - één link terug, die zowel afbreekt als terugdraait;
 * - geen enkel token in de database of in het logboek in platte vorm.
 *
 * Zie docs/security/inlogadres-wijzigen.md.
 */
class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    private const NIEUW = 'nieuw@voorbeeld.nl';

    /**
     * Het authenticator-secret van de laatst aangemaakte eigenaar.
     *
     * Op de test en niet op het model: een losse eigenschap op een
     * Eloquent-model gaat bij de eerstvolgende `save()` mee de query in,
     * en dan valt de test om op een kolom die niet bestaat.
     */
    private string $secret = '';

    /* --- Opzet ------------------------------------------------------------ */

    /** Een eigenaar met tweestapsverificatie, maar nog zonder verse code. */
    private function eigenaar(): User
    {
        $this->secret = (new Google2FA)->generateSecretKey();

        $user = User::factory()->create(['email' => 'oud@voorbeeld.nl']);

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($this->secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    /** Een verse code invoeren, zoals het scherm dat ook doet. */
    private function bevestigDeCode(User $eigenaar): void
    {
        $this->actingAs($eigenaar)->post(route('security.two-factor.confirm.store'), [
            'code' => (new Google2FA)->getCurrentOtp($this->secret),
        ]);
    }

    /** Dezelfde eigenaar, nu mét een verse code in de sessie. */
    private function bevestigdeEigenaar(): User
    {
        $user = $this->eigenaar();

        $this->bevestigDeCode($user);

        return $user;
    }

    /**
     * De aanvraag doen en het platte bevestigingstoken teruggeven.
     *
     * Dat token bestaat maar op één plek -- in de mail -- precies zoals in
     * het echt. De test leest het daar dus ook vandaan.
     *
     * @return array{0: string, 1: string} het bevestig- en het hersteltoken
     */
    private function vraagAan(User $eigenaar, string $naar = self::NIEUW): array
    {
        $this->actingAs($eigenaar)
            ->post(route('inlogadres.store'), [
                'email' => $naar,
                'password' => 'password',
            ])
            ->assertSessionHasNoErrors();

        $bevestig = '';
        $herstel = '';

        Mail::assertSent(InlogadresBevestigenMail::class, function ($mail) use (&$bevestig) {
            $bevestig = $this->tokenUit($mail->link);

            return true;
        });

        Mail::assertSent(InlogadresAangevraagdMail::class, function ($mail) use (&$herstel) {
            $herstel = $this->tokenUit($mail->afbreekLink);

            return true;
        });

        return [$bevestig, $herstel];
    }

    private function tokenUit(string $link): string
    {
        return (string) mb_substr($link, mb_strrpos($link, '/') + 1);
    }

    /* --- De sloten vóór de aanvraag --------------------------------------- */

    public function test_a_guest_cannot_request_a_change(): void
    {
        Mail::fake();

        $this->post(route('inlogadres.store'), [
            'email' => self::NIEUW,
            'password' => 'password',
        ])->assertRedirect(route('login'));

        $this->assertDatabaseCount('email_changes', 0);
        Mail::assertNothingSent();
    }

    public function test_without_a_fresh_authenticator_code_the_request_is_not_accepted(): void
    {
        Mail::fake();

        $eigenaar = $this->eigenaar();

        $this->actingAs($eigenaar)
            ->post(route('inlogadres.store'), [
                'email' => self::NIEUW,
                'password' => 'password',
            ])
            ->assertRedirect(route('security.two-factor.confirm'));

        $this->assertDatabaseCount('email_changes', 0);
        Mail::assertNothingSent();
    }

    public function test_the_window_itself_is_behind_the_authenticator(): void
    {
        $eigenaar = $this->eigenaar();

        /*
         * De knop op het profiel gaat eerst langs deze route. Zou die open
         * staan, dan vraagt het portaal de code pas bij het versturen en
         * is het ingevulde formulier weg.
         */
        $this->actingAs($eigenaar)
            ->get(route('inlogadres.create'))
            ->assertRedirect(route('security.two-factor.confirm'));

        $this->bevestigDeCode($eigenaar);

        $this->actingAs($eigenaar)
            ->get(route('inlogadres.create'))
            ->assertRedirect(route('profile.edit'))
            ->assertSessionHas('inlogadresVenster', true);
    }

    public function test_a_user_without_two_factor_is_sent_to_the_security_screen(): void
    {
        Mail::fake();

        $zonder = User::factory()->create();

        $this->actingAs($zonder)
            ->post(route('inlogadres.store'), [
                'email' => self::NIEUW,
                'password' => 'password',
            ])
            ->assertRedirect(route('security.edit'));

        $this->assertDatabaseCount('email_changes', 0);
        Mail::assertNothingSent();
    }

    public function test_the_wrong_password_stops_the_request(): void
    {
        Mail::fake();

        $this->actingAs($this->bevestigdeEigenaar())
            ->post(route('inlogadres.store'), [
                'email' => self::NIEUW,
                'password' => 'niet-het-wachtwoord',
            ])
            ->assertSessionHasErrors('password');

        $this->assertDatabaseCount('email_changes', 0);
        Mail::assertNothingSent();
    }

    public function test_the_current_address_is_refused_as_a_new_address(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();

        $this->actingAs($eigenaar)
            ->post(route('inlogadres.store'), [
                'email' => $eigenaar->email,
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('email_changes', 0);
        Mail::assertNothingSent();
    }

    public function test_an_address_that_is_not_an_address_is_refused(): void
    {
        Mail::fake();

        $this->actingAs($this->bevestigdeEigenaar())
            ->post(route('inlogadres.store'), [
                'email' => 'geen adres',
                'password' => 'password',
            ])
            ->assertSessionHasErrors('email');

        $this->assertDatabaseCount('email_changes', 0);
        Mail::assertNothingSent();
    }

    /* --- De aanvraag ------------------------------------------------------- */

    public function test_a_request_does_not_touch_the_login_address_yet(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();

        $this->vraagAan($eigenaar);

        $this->assertSame('oud@voorbeeld.nl', $eigenaar->refresh()->email);

        $this->assertDatabaseHas('email_changes', [
            'user_id' => $eigenaar->id,
            'from_email' => 'oud@voorbeeld.nl',
            'to_email' => self::NIEUW,
            'confirmed_at' => null,
            'reverted_at' => null,
        ]);
    }

    public function test_both_mailboxes_hear_about_it(): void
    {
        Mail::fake();

        $this->vraagAan($this->bevestigdeEigenaar());

        Mail::assertSent(
            InlogadresBevestigenMail::class,
            fn ($mail) => $mail->hasTo(self::NIEUW),
        );

        Mail::assertSent(
            InlogadresAangevraagdMail::class,
            fn ($mail) => $mail->hasTo('oud@voorbeeld.nl'),
        );
    }

    /**
     * De drie sjablonen worden echt opgebouwd.
     *
     * `Mail::fake()` rendert niets, dus zonder deze test zou een typefout
     * in een Blade-bestand pas bij de eerste echte aanvraag opvallen --
     * en dan staat de eigenaar te wachten op een mail die nooit komt.
     */
    public function test_all_three_mails_can_actually_be_rendered(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig, $herstel] = $this->vraagAan($eigenaar);

        $this->get(route('inlogadres.bevestigen', $bevestig));

        $verzonden = [
            InlogadresBevestigenMail::class => $bevestig,
            InlogadresAangevraagdMail::class => $herstel,
            InlogadresGewijzigdMail::class => null,
        ];

        foreach ($verzonden as $klasse => $token) {
            Mail::assertSent($klasse, function (Mailable $mail) use ($token) {
                $html = $mail->render();

                $this->assertStringContainsString('<html', mb_strtolower($html));

                if ($token !== null) {
                    $this->assertStringContainsString($token, $html);
                }

                return true;
            });
        }
    }

    public function test_the_tokens_are_never_stored_in_plain_text(): void
    {
        Mail::fake();

        [$bevestig, $herstel] = $this->vraagAan($this->bevestigdeEigenaar());

        $rij = EmailChange::query()->firstOrFail();

        $this->assertNotSame($bevestig, $rij->confirm_token);
        $this->assertNotSame($herstel, $rij->revert_token);
        $this->assertSame(EmailChange::hash($bevestig), $rij->confirm_token);
        $this->assertSame(EmailChange::hash($herstel), $rij->revert_token);
    }

    public function test_no_token_ends_up_in_the_security_log(): void
    {
        Mail::fake();

        [$bevestig, $herstel] = $this->vraagAan($this->bevestigdeEigenaar());

        $this->get(route('inlogadres.bevestigen', $bevestig));
        $this->get(route('inlogadres.terugdraaien', $herstel));

        $alles = SecurityEvent::query()->get()
            ->map(fn (SecurityEvent $gebeurtenis) => (string) json_encode($gebeurtenis->context))
            ->implode(' ');

        $this->assertStringNotContainsString($bevestig, $alles);
        $this->assertStringNotContainsString($herstel, $alles);
    }

    public function test_the_request_is_logged_with_the_new_address(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        $this->vraagAan($eigenaar);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::EmailChangeRequested->value,
            'outcome' => SecurityOutcome::Success->value,
            'user_id' => $eigenaar->id,
        ]);
    }

    public function test_a_second_request_kills_the_first_confirmation_link(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();

        [$eerste] = $this->vraagAan($eigenaar, 'eerste@voorbeeld.nl');
        [$tweede] = $this->vraagAan($eigenaar, 'tweede@voorbeeld.nl');

        /*
         * Twee geldige links tegelijk zou betekenen dat wie het eerst
         * klikt bepaalt waar de post heen gaat.
         */
        $this->get(route('inlogadres.bevestigen', $eerste));
        $this->assertSame('oud@voorbeeld.nl', $eigenaar->refresh()->email);

        $this->get(route('inlogadres.bevestigen', $tweede));
        $this->assertSame('tweede@voorbeeld.nl', $eigenaar->refresh()->email);
    }

    /* --- Bevestigen --------------------------------------------------------- */

    public function test_the_confirmation_link_swaps_the_address(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig] = $this->vraagAan($eigenaar);

        $this->get(route('inlogadres.bevestigen', $bevestig))
            ->assertRedirect(route('login'));

        $eigenaar->refresh();

        $this->assertSame(self::NIEUW, $eigenaar->email);
        $this->assertNotNull($eigenaar->email_verified_at);

        // En het oude postvak hoort te weten dat het zijn account kwijt is.
        Mail::assertSent(
            InlogadresGewijzigdMail::class,
            fn ($mail) => $mail->hasTo('oud@voorbeeld.nl'),
        );

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::EmailChangeConfirmed->value,
            'outcome' => SecurityOutcome::Success->value,
        ]);
    }

    public function test_the_confirmation_link_works_only_once(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig] = $this->vraagAan($eigenaar);

        $this->get(route('inlogadres.bevestigen', $bevestig));

        /*
         * Terug naar het oude adres, buiten de stroom om, om te zien of de
         * link hem een tweede keer zou omzetten.
         *
         * Eerst `refresh()`: zonder dat staat er in het geheugen nog het
         * oude adres, is er niets gewijzigd en schrijft `save()` niets weg.
         * De test zou dan slagen op de verkeerde grond.
         */
        $eigenaar->refresh()->forceFill(['email' => 'oud@voorbeeld.nl'])->save();

        $this->get(route('inlogadres.bevestigen', $bevestig));

        $this->assertSame('oud@voorbeeld.nl', $eigenaar->refresh()->email);
    }

    public function test_an_expired_confirmation_link_does_nothing(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig] = $this->vraagAan($eigenaar);

        $this->travel(EmailChange::BEVESTIGEN_GELDIG + 1)->minutes();

        $this->get(route('inlogadres.bevestigen', $bevestig));

        $this->assertSame('oud@voorbeeld.nl', $eigenaar->refresh()->email);
    }

    /**
     * Tussen de aanvraag en de klik kan het adres bezet raken.
     *
     * Via Beheer → Gebruikers kan er een tweede account op dat adres zijn
     * gezet. Zonder de controle in `confirm()` loopt dit op een unieke
     * index stuk, en dat is een foutpagina op een openbare link.
     */
    public function test_an_address_that_got_taken_in_the_meantime_is_refused(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig] = $this->vraagAan($eigenaar);

        User::factory()->create(['email' => self::NIEUW]);

        $this->get(route('inlogadres.bevestigen', $bevestig))
            ->assertRedirect(route('login'));

        $this->assertSame('oud@voorbeeld.nl', $eigenaar->refresh()->email);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::EmailChangeConfirmed->value,
            'outcome' => SecurityOutcome::Failure->value,
        ]);
    }

    public function test_an_unknown_confirmation_token_does_nothing(): void
    {
        $this->get(route('inlogadres.bevestigen', 'dit-bestaat-niet'))
            ->assertRedirect(route('login'));

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::EmailChangeConfirmed->value,
            'outcome' => SecurityOutcome::Failure->value,
        ]);
    }

    /* --- De weg terug -------------------------------------------------------- */

    public function test_the_revert_link_cancels_a_request_that_is_not_confirmed_yet(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig, $herstel] = $this->vraagAan($eigenaar);

        $this->get(route('inlogadres.terugdraaien', $herstel))
            ->assertRedirect(route('login'));

        // En de bevestigingslink is daarmee dood.
        $this->get(route('inlogadres.bevestigen', $bevestig));

        $this->assertSame('oud@voorbeeld.nl', $eigenaar->refresh()->email);
    }

    public function test_the_same_link_puts_the_old_address_back_after_a_confirmation(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig, $herstel] = $this->vraagAan($eigenaar);

        $this->get(route('inlogadres.bevestigen', $bevestig));
        $this->assertSame(self::NIEUW, $eigenaar->refresh()->email);

        $this->get(route('inlogadres.terugdraaien', $herstel));

        $eigenaar->refresh();

        $this->assertSame('oud@voorbeeld.nl', $eigenaar->email);
        $this->assertNotNull($eigenaar->email_verified_at);

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::EmailChangeReverted->value,
            'outcome' => SecurityOutcome::Success->value,
        ]);
    }

    public function test_the_revert_link_still_works_long_after_the_confirmation(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig, $herstel] = $this->vraagAan($eigenaar);

        $this->get(route('inlogadres.bevestigen', $bevestig));

        /*
         * Het vangnet is er juist voor wie zelden inlogt en het pas na een
         * week merkt. Zou de termijn vanaf de aanvraag lopen, dan was hij
         * dan al grotendeels op.
         */
        $this->travel(EmailChange::HERSTELLEN_GELDIG - 1)->days();

        $this->get(route('inlogadres.terugdraaien', $herstel));

        $this->assertSame('oud@voorbeeld.nl', $eigenaar->refresh()->email);
    }

    public function test_the_revert_link_works_only_once(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig, $herstel] = $this->vraagAan($eigenaar);

        $this->get(route('inlogadres.bevestigen', $bevestig));
        $this->get(route('inlogadres.terugdraaien', $herstel));

        // Zie de toelichting bij de bevestigingslink: eerst verversen,
        // anders schrijft `save()` niets weg.
        $eigenaar->refresh()->forceFill(['email' => self::NIEUW])->save();

        $this->get(route('inlogadres.terugdraaien', $herstel));

        $this->assertSame(self::NIEUW, $eigenaar->refresh()->email);
    }

    public function test_an_expired_revert_link_does_nothing(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig, $herstel] = $this->vraagAan($eigenaar);

        $this->get(route('inlogadres.bevestigen', $bevestig));

        $this->travel(EmailChange::HERSTELLEN_GELDIG + 1)->days();

        $this->get(route('inlogadres.terugdraaien', $herstel));

        $this->assertSame(self::NIEUW, $eigenaar->refresh()->email);
    }

    /* --- Het scherm ---------------------------------------------------------- */

    public function test_the_profile_screen_shows_a_pending_change_without_the_token(): void
    {
        Mail::fake();

        $eigenaar = $this->bevestigdeEigenaar();
        [$bevestig] = $this->vraagAan($eigenaar);

        $this->actingAs($eigenaar)
            ->get(route('profile.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/Profile')
                ->where('openstaandeWijziging.naar', self::NIEUW)
                ->missing('openstaandeWijziging.confirm_token')
                ->missing('openstaandeWijziging.revert_token')
            );

        $antwoord = $this->actingAs($eigenaar)->get(route('profile.edit'));

        $this->assertStringNotContainsString($bevestig, $antwoord->getContent() ?: '');
    }

    public function test_the_profile_screen_shows_nothing_when_there_is_no_request(): void
    {
        $this->actingAs($this->eigenaar())
            ->get(route('profile.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/Profile')
                ->where('openstaandeWijziging', null)
                ->where('openInlogadresVenster', false)
            );
    }
}
