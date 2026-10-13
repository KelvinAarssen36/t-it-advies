<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Toast;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * De meldingen rechtsonder.
 *
 * Wat hier wordt bewaakt is de **soort**, niet de tekst. Een handeling die
 * iets weghaalt hoort een andere melding te geven dan een die iets wijzigt,
 * want dat verschil is precies waar deze meldingen voor zijn: zien wat er
 * gebeurde zonder het te hoeven lezen.
 *
 * Zie docs/architecture/meldingen.md.
 */
class ToastTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
    }

    /**
     * Een beheerder die zojuist een geldige code heeft ingevoerd.
     *
     * Verwijderen is een gevoelige actie en vraagt om een verse code uit de
     * authenticator; zonder die stap komt het verzoek niet eens bij de
     * controller en is er dus ook geen melding om te controleren. Zie
     * docs/security/gevoelige-acties.md.
     */
    private function beheerder(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        $secret = app(Google2FA::class)->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($user)->post(route('security.two-factor.confirm.store'), [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ]);

        return $user;
    }

    public function test_changing_your_profile_says_it_was_changed(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Nieuwe naam',
                'email' => $user->email,
            ]);

        $this->assertSame(
            Toast::BIJGEWERKT,
            session('inertia.flash_data.toast.type'),
        );
    }

    public function test_deleting_an_account_says_it_was_deleted(): void
    {
        $beheerder = $this->beheerder();
        $doelwit = User::factory()->create();

        $this->actingAs($beheerder)
            ->delete(route('admin.users.destroy', $doelwit));

        $this->assertSame(
            Toast::VERWIJDERD,
            session('inertia.flash_data.toast.type'),
        );
    }

    public function test_a_refused_action_is_an_error_and_not_a_notice(): void
    {
        /*
         * Je dacht dat er iets zou gebeuren en dat gebeurde niet. Dat moet
         * er anders uitzien dan een bevestiging, anders lees je eroverheen
         * en denk je dat het gelukt is.
         */
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)
            ->delete(route('admin.users.destroy', $beheerder));

        $this->assertSame(Toast::FOUT, session('inertia.flash_data.toast.type'));
        $this->assertNotNull($beheerder->fresh());
    }

    public function test_a_description_is_optional(): void
    {
        Toast::melding('Alleen een titel.');

        $this->assertSame(
            ['type' => Toast::MELDING, 'message' => 'Alleen een titel.'],
            session('inertia.flash_data.toast'),
        );

        Toast::aangemaakt('Met uitleg.', 'En die staat eronder.');

        $this->assertSame(
            'En die staat eronder.',
            session('inertia.flash_data.toast.description'),
        );
    }
}
