<?php

namespace Tests\Feature\Security;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * De verplichting om tweestapsverificatie in te stellen.
 *
 * De volgorde van de poortjes in EnsureTwoFactorIsConfigured is het hele
 * ontwerp, en elk poortje heeft hier zijn eigen test. Vooral de
 * uitzonderingen zijn belangrijk: zonder die zou de middleware je wegsturen
 * van de pagina waar hij je naartoe stuurt, en zit je in een lus.
 */
class TwoFactorRequiredTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        config(['security.two_factor.required' => true]);
    }

    private function userWithoutTwoFactor(): User
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    private function userWithTwoFactor(): User
    {
        $user = $this->userWithoutTwoFactor();

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(
                app(Google2FA::class)->generateSecretKey()
            ),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $user;
    }

    public function test_the_portal_is_closed_until_two_factor_is_confirmed(): void
    {
        $this->actingAs($this->userWithoutTwoFactor())
            ->get(route('dashboard'))
            ->assertRedirect(route('security.two-factor.setup'));
    }

    public function test_the_admin_area_and_the_settings_are_closed_too(): void
    {
        $user = $this->userWithoutTwoFactor();

        $this->actingAs($user)
            ->get(route('admin.dashboard'))
            ->assertRedirect(route('security.two-factor.setup'));

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertRedirect(route('security.two-factor.setup'));
    }

    public function test_the_setup_page_itself_stays_reachable(): void
    {
        // Zonder deze uitzondering stuurt de middleware je weg van de
        // pagina waar hij je naartoe stuurt, en blijf je rondjes draaien.
        $this->actingAs($this->userWithoutTwoFactor())
            ->get(route('security.two-factor.setup'))
            ->assertOk();
    }

    public function test_the_setup_page_knows_whether_the_password_was_just_confirmed(): void
    {
        $user = $this->userWithoutTwoFactor();

        // Fortify vraagt om het wachtwoord voordat 2FA aan mag, en voert
        // het onderschepte verzoek daarna niet opnieuw uit. Zonder dit
        // gegeven ziet de pagina er na het bevestigen precies hetzelfde uit
        // en lijkt het alsof er niets is gebeurd.
        $this->actingAs($user)
            ->get(route('security.two-factor.setup'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('passwordConfirmed', false));

        $this->actingAs($user)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get(route('security.two-factor.setup'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('passwordConfirmed', true));
    }

    public function test_a_long_expired_password_confirmation_does_not_count(): void
    {
        $this->actingAs($this->userWithoutTwoFactor())
            ->withSession([
                'auth.password_confirmed_at' => time() - config('auth.password_timeout') - 60,
            ])
            ->get(route('security.two-factor.setup'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('passwordConfirmed', false));
    }

    public function test_logging_out_stays_possible(): void
    {
        $this->actingAs($this->userWithoutTwoFactor())
            ->post(route('logout'))
            ->assertRedirect();

        $this->assertGuest();
    }

    public function test_a_confirmed_user_passes_straight_through(): void
    {
        $this->actingAs($this->userWithTwoFactor())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_a_secret_without_confirmation_is_not_enough(): void
    {
        $user = $this->userWithoutTwoFactor();

        // Wie de instelpagina opent en afhaakt vóór het intypen van de code
        // heeft wel een geheim, maar is niet beveiligd.
        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt(
                app(Google2FA::class)->generateSecretKey()
            ),
            'two_factor_confirmed_at' => null,
        ])->save();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('security.two-factor.setup'));
    }

    public function test_an_unverified_address_is_left_to_the_other_middleware(): void
    {
        $user = User::factory()->unverified()->create();

        // Twee verplichtingen die allebei omleiden, leiden tot een lus.
        // Deze middleware laat de e-mailverificatie voorgaan.
        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_a_guest_still_goes_to_the_login_screen(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_nothing_is_enforced_when_the_requirement_is_off(): void
    {
        config(['security.two_factor.required' => false]);

        $this->actingAs($this->userWithoutTwoFactor())
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_the_original_destination_is_remembered(): void
    {
        $user = $this->userWithoutTwoFactor();

        // De middleware gebruikt redirect()->guest(), dus de bestemming
        // blijft in de sessie staan.
        $this->actingAs($user)->get(route('admin.mail.index'));

        $this->assertSame(
            route('admin.mail.index'),
            session('url.intended'),
        );
    }

    public function test_after_setting_it_up_you_go_via_the_loading_screen(): void
    {
        // Het laadscherm pakt de onthouden bestemming zelf op; dat wordt
        // getest in PortalEntryTest.
        $this->actingAs($this->userWithTwoFactor())
            ->get(route('security.two-factor.setup.finish'))
            ->assertRedirect(route('portal.enter'));
    }
}
