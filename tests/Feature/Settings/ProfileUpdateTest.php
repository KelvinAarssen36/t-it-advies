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
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged()
    {
        $user = User::factory()->create();

        $response = $this
            ->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('profile.edit'));

        $this->assertNotNull($user->refresh()->email_verified_at);
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
