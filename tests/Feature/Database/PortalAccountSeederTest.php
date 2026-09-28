<?php

namespace Tests\Feature\Database;

use App\Models\User;
use Database\Seeders\PortalAccountSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Tests\TestCase;

/**
 * Het account van de eigenaar is geen testdata: het hoort in elke omgeving
 * te bestaan. Deze seeder draait dus bij elke deploy, en daarmee is
 * idempotentie geen nettigheid maar een voorwaarde.
 */
class PortalAccountSeederTest extends TestCase
{
    use RefreshDatabase;

    private const WACHTWOORD = 'een-wachtwoord-voor-deze-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        config([
            'security.portal_account.name' => 'Eigenaar',
            'security.portal_account.email' => 'Eigenaar@Voorbeeld.nl',
            'security.portal_account.password' => self::WACHTWOORD,
            'security.portal_account.role' => 'admin',
        ]);
    }

    public function test_it_creates_the_account_with_a_role_and_a_verified_address(): void
    {
        $this->seed(PortalAccountSeeder::class);

        $gebruiker = User::sole();

        // Kleine letters, want SQLite vergelijkt hoofdlettergevoelig en dan
        // zou inloggen met het adres zoals je het typt kunnen mislukken.
        $this->assertSame('eigenaar@voorbeeld.nl', $gebruiker->email);
        $this->assertTrue($gebruiker->hasRole('admin'));
        $this->assertNotNull($gebruiker->email_verified_at);
        $this->assertTrue(Hash::check(self::WACHTWOORD, $gebruiker->password));
    }

    public function test_running_it_twice_leaves_one_account(): void
    {
        $this->seed(PortalAccountSeeder::class);
        $this->seed(PortalAccountSeeder::class);

        $this->assertSame(1, User::query()->count());
    }

    public function test_it_never_resets_a_password_that_was_changed(): void
    {
        $this->seed(PortalAccountSeeder::class);

        $gebruiker = User::sole();
        $gebruiker->forceFill(['password' => Hash::make('door-de-eigenaar-zelf-gewijzigd')])->save();

        $this->seed(PortalAccountSeeder::class);

        // Zou de seeder bijwerken, dan draait elke deploy het wachtwoord
        // terug en komt de eigenaar er ineens niet meer in.
        $this->assertTrue(
            Hash::check('door-de-eigenaar-zelf-gewijzigd', User::sole()->password),
        );
    }

    public function test_it_restores_a_role_that_went_missing(): void
    {
        $this->seed(PortalAccountSeeder::class);

        User::sole()->syncRoles([]);

        $this->seed(PortalAccountSeeder::class);

        $this->assertTrue(User::sole()->hasRole('admin'));
    }

    public function test_it_stops_when_no_password_is_configured(): void
    {
        config(['security.portal_account.password' => null]);

        $this->expectException(RuntimeException::class);

        $this->seed(PortalAccountSeeder::class);
    }
}
