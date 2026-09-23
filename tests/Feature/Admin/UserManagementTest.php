<?php

namespace Tests\Feature\Admin;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Gebruikersbeheer is de eerste plek in deze applicatie waar echt iets kapot
 * kan: rollen uitdelen en accounts verwijderen.
 *
 * Deze tests leggen vast dat kijken genoeg heeft aan een recht, maar dat
 * wijzigen daarnaast een verse authenticator-code vraagt -- en dat de twee
 * vangnetten werken: je eigen account en de laatste beheerder blijven staan.
 */
class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * Zet bevestigde 2FA op een gebruiker en geeft het geheim terug.
     */
    private function enableTwoFactor(User $user): string
    {
        $secret = app(Google2FA::class)->generateSecretKey();

        $user->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_recovery_codes' => Fortify::currentEncrypter()->encrypt(
                (string) json_encode(['abcdefghij-klmnopqrst'])
            ),
            'two_factor_confirmed_at' => now(),
        ])->save();

        return $secret;
    }

    /**
     * Een beheerder die zojuist een geldige code heeft ingevoerd.
     */
    private function confirmedAdmin(): User
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->confirm($admin, $this->enableTwoFactor($admin));

        return $admin;
    }

    private function confirm(User $user, string $secret): void
    {
        $this->actingAs($user)->post(route('security.two-factor.confirm.store'), [
            'code' => app(Google2FA::class)->getCurrentOtp($secret),
        ]);
    }

    public function test_a_user_without_the_permission_cannot_see_the_overview(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view security log');

        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_an_admin_can_see_the_overview(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk();
    }

    public function test_changing_roles_asks_for_a_fresh_code_first(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('admin');
        $this->enableTwoFactor($admin);

        $target = User::factory()->create();

        $this->actingAs($admin)
            ->put(route('admin.users.roles.update', $target), ['roles' => ['developer']])
            ->assertRedirect(route('security.two-factor.confirm'));

        $this->assertFalse($target->fresh()?->hasRole('developer'));
    }

    public function test_roles_are_changed_and_logged_after_confirmation(): void
    {
        $admin = $this->confirmedAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.roles.update', $target), ['roles' => ['developer']])
            ->assertRedirect(route('admin.users.index'));

        $this->assertTrue($target->fresh()?->hasRole('developer'));

        $this->assertDatabaseHas('security_events', [
            'event' => SecurityEventType::UserRolesChanged->value,
            'user_id' => $admin->id,
        ]);
    }

    public function test_an_unknown_role_is_refused(): void
    {
        $admin = $this->confirmedAdmin();
        $target = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.roles.update', $target), ['roles' => ['superuser']])
            ->assertSessionHasErrors('roles.0');

        $this->assertSame([], $target->fresh()?->roles->pluck('name')->all());
    }

    public function test_you_cannot_change_your_own_roles(): void
    {
        $admin = $this->confirmedAdmin();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.roles.update', $admin), ['roles' => []]);

        // Wie zichzelf zijn rechten afneemt, kan het niet meer terugdraaien.
        $this->assertTrue($admin->fresh()?->hasRole('admin'));
    }

    public function test_you_cannot_delete_yourself(): void
    {
        $admin = $this->confirmedAdmin();

        $this->actingAs($admin)
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $admin));

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_the_last_admin_cannot_be_removed(): void
    {
        $onlyAdmin = User::factory()->create();
        $onlyAdmin->assignRole('admin');

        // De uitvoerder mag gebruikers beheren, maar is zelf geen beheerder.
        $actor = User::factory()->create();
        $actor->givePermissionTo('manage users');
        $this->confirm($actor, $this->enableTwoFactor($actor));

        $this->actingAs($actor)
            ->from(route('admin.users.index'))
            ->delete(route('admin.users.destroy', $onlyAdmin));

        $this->assertDatabaseHas('users', ['id' => $onlyAdmin->id]);

        $this->actingAs($actor)
            ->from(route('admin.users.index'))
            ->put(route('admin.users.roles.update', $onlyAdmin), ['roles' => []]);

        $this->assertTrue($onlyAdmin->fresh()?->hasRole('admin'));
    }

    public function test_deleting_a_user_is_logged_with_an_identifiable_trail(): void
    {
        $admin = $this->confirmedAdmin();
        $target = User::factory()->create(['email' => 'weg@example.com']);

        $this->actingAs($admin)
            ->delete(route('admin.users.destroy', $target))
            ->assertRedirect(route('admin.users.index'));

        $this->assertDatabaseMissing('users', ['id' => $target->id]);

        $logged = SecurityEvent::query()
            ->where('event', SecurityEventType::UserDeleted->value)
            ->sole();

        // Het id alleen zegt niets meer zodra de rij weg is; daarom staat het
        // adres van het verwijderde account erbij.
        $this->assertSame($admin->id, $logged->user_id);
        $this->assertSame('weg@example.com', $logged->context['target_email'] ?? null);
    }
}
