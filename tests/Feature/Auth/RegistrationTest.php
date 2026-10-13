<?php

namespace Tests\Feature\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;
use Tests\TestCase;

/**
 * Registratie staat uit, en dat is een keuze en geen vergetelheid.
 *
 * Deze site heeft één gebruiker: de eigenaar. Een open registratieformulier
 * zou vreemden een account geven op een applicatie die verder alleen voor hem
 * is. Accounts maak je met `php artisan user:create`.
 *
 * Zet iemand de feature terug aan, dan faalt deze test. Dat is de bedoeling:
 * het hoort een bewuste beslissing te zijn, niet een regel die ongemerkt
 * terugkomt bij het bijwerken van de starter kit.
 */
class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_is_disabled(): void
    {
        $this->assertFalse(Features::enabled(Features::registration()));
    }

    public function test_there_is_no_registration_route(): void
    {
        $this->assertFalse(Route::has('register'));

        $this->get('/register')->assertNotFound();
    }
}
