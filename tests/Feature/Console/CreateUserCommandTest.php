<?php

namespace Tests\Feature\Console;

use App\Enums\SecurityEventType;
use App\Models\SecurityEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Registratie via de website staat uit, dus dit commando is de enige manier
 * om een account te maken. Gaat hier iets stuk, dan kan niemand meer bij zijn
 * eigen site -- vandaar dat het volledig getest is.
 */
class CreateUserCommandTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'geheim-genoeg-voor-een-test';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_it_creates_a_verified_account_with_a_role(): void
    {
        $this->artisan('user:create', [
            '--name' => 'De Klant',
            '--email' => 'klant@example.com',
            '--role' => 'admin',
        ])
            ->expectsQuestion('Wachtwoord', self::PASSWORD)
            ->expectsQuestion('Wachtwoord nogmaals', self::PASSWORD)
            ->assertSuccessful();

        $user = User::where('email', 'klant@example.com')->sole();

        $this->assertTrue($user->hasRole('admin'));

        // Zonder geverifieerd e-mailadres komt hij niet in het beheergedeelte,
        // en op het moment van aanmaken staat de mailprovider vaak nog niet.
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_it_refuses_an_address_that_already_exists(): void
    {
        User::factory()->create(['email' => 'klant@example.com']);

        $this->artisan('user:create', [
            '--name' => 'Nog Een',
            '--email' => 'klant@example.com',
            '--role' => 'admin',
        ])
            ->expectsQuestion('Wachtwoord', self::PASSWORD)
            ->expectsQuestion('Wachtwoord nogmaals', self::PASSWORD)
            ->assertFailed();

        $this->assertSame(1, User::where('email', 'klant@example.com')->count());
    }

    public function test_it_refuses_an_unknown_role(): void
    {
        $this->artisan('user:create', [
            '--name' => 'De Klant',
            '--email' => 'klant@example.com',
            '--role' => 'superuser',
        ])
            ->expectsQuestion('Wachtwoord', self::PASSWORD)
            ->expectsQuestion('Wachtwoord nogmaals', self::PASSWORD)
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'klant@example.com']);
    }

    public function test_it_refuses_a_mistyped_confirmation(): void
    {
        $this->artisan('user:create', [
            '--name' => 'De Klant',
            '--email' => 'klant@example.com',
            '--role' => 'admin',
        ])
            ->expectsQuestion('Wachtwoord', self::PASSWORD)
            ->expectsQuestion('Wachtwoord nogmaals', 'iets-anders-getypt')
            ->assertFailed();

        $this->assertDatabaseMissing('users', ['email' => 'klant@example.com']);
    }

    public function test_the_password_never_reaches_the_security_log(): void
    {
        $this->artisan('user:create', [
            '--name' => 'De Klant',
            '--email' => 'klant@example.com',
            '--role' => 'admin',
        ])
            ->expectsQuestion('Wachtwoord', self::PASSWORD)
            ->expectsQuestion('Wachtwoord nogmaals', self::PASSWORD)
            ->assertSuccessful();

        $event = SecurityEvent::query()
            ->where('event', SecurityEventType::UserCreated->value)
            ->sole();

        $this->assertStringNotContainsString(
            self::PASSWORD,
            (string) json_encode($event->context),
        );
    }
}
