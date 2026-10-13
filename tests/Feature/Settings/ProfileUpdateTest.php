<?php

namespace Tests\Feature\Settings;

use App\Http\Controllers\Settings\ProfileController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('Test User', $user->refresh()->name);
    }

    /**
     * Het profielformulier raakt het inlogadres niet meer aan.
     *
     * Het stond hier gewoon tussen de velden: opslaan met een typefout en
     * het enige account van het portaal wees naar een postvak dat niet
     * bestaat. Nu loopt dat langs EmailChangeController, met een
     * authenticator, een wachtwoord, een bevestiging en een weg terug.
     *
     * Deze test is er vooral tegen onszelf: `email` terugzetten in
     * ProfileUpdateRequest is één regel, en dan is het hele vangnet weg
     * zonder dat er iets kapot lijkt.
     *
     * Zie docs/security/inlogadres-wijzigen.md.
     */
    public function test_the_profile_form_cannot_change_the_login_address()
    {
        $user = User::factory()->create(['email' => 'eigenaar@voorbeeld.nl']);

        $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => 'gekaapt@voorbeeld.nl',
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('eigenaar@voorbeeld.nl', $user->email);
        $this->assertNotNull($user->email_verified_at);
    }

    /**
     * Je eigen account verwijderen kan niet, en dat moet zo blijven.
     *
     * Dit portaal heeft één account en registratie staat uit. Wie dat
     * account weghaalt sluit zichzelf buiten, en er is daarna niemand meer
     * die kan inloggen -- ook wij niet.
     *
     * De starter kit levert dit scherm standaard mee, dus dit is precies
     * het soort ding dat bij een update ongemerkt terugkomt.
     */
    public function test_there_is_no_way_to_delete_your_own_account()
    {
        $this->assertFalse(
            Route::has('profile.destroy'),
            'De route om je eigen account te verwijderen is terug.',
        );

        $this->assertFalse(
            method_exists(ProfileController::class, 'destroy'),
            'ProfileController kan weer accounts verwijderen.',
        );

        $user = User::factory()->create();

        /*
         * En de deur zit ook echt dicht, niet alleen de knop is weg.
         *
         * 405 en geen 404: het adres /settings/profile bestaat nog wel, voor
         * bekijken en bijwerken. Alleen de DELETE erop is er niet meer.
         */
        $this->actingAs($user)
            ->delete('/settings/profile', ['password' => 'password'])
            ->assertStatus(405);

        $this->assertNotNull($user->fresh());
    }
}
