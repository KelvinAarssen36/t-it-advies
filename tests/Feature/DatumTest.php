<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Datum;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Datums en tijden in de taal van de gebruiker.
 *
 * Hier stond overal `toDateTimeString()`, en dat geeft `2026-09-28 10:14:03`
 * -- het jaar eerst, met seconden erbij. Dat is het formaat van een
 * database en niet van een mens.
 *
 * Zie App\Support\Datum en docs/architecture/vertalingen.md.
 */
class DatumTest extends TestCase
{
    use RefreshDatabase;

    private function moment(): CarbonImmutable
    {
        return CarbonImmutable::create(2026, 9, 28, 10, 14, 3);
    }

    public function test_dutch_puts_the_day_first(): void
    {
        $this->app->setLocale('nl');

        $this->assertSame('28 sep. 2026, 10:14', Datum::tijdstip($this->moment()));
        $this->assertSame('28 sep. 2026', Datum::dag($this->moment()));
    }

    public function test_english_says_it_in_english(): void
    {
        $this->app->setLocale('en');

        $this->assertSame('28 Sep 2026, 10:14', Datum::tijdstip($this->moment()));
    }

    public function test_the_seconds_are_gone(): void
    {
        // Niemand die in een logboek kijkt heeft iets aan seconden, en ze
        // maken de kolom breder dan de rest van de regel.
        $this->app->setLocale('nl');

        $this->assertStringNotContainsString('03', (string) Datum::tijdstip($this->moment()));
    }

    public function test_nothing_in_means_nothing_out(): void
    {
        // Een datum die er niet is hoort leeg te blijven en geen "nu" te
        // worden; het scherm zet er zelf een streepje voor in de plaats.
        $this->assertNull(Datum::tijdstip(null));
        $this->assertNull(Datum::dag(null));
        $this->assertNull(Datum::geleden(null));
    }

    public function test_how_long_ago_follows_the_language(): void
    {
        /*
         * Carbon heeft een eigen taal, los van die van de applicatie. Zonder
         * de regel in SetLocale blijft dit "2 hours ago" in een Nederlands
         * portaal.
         */
        $this->travelTo(CarbonImmutable::create(2026, 9, 28, 12, 0));

        $user = User::factory()->create(['locale' => 'nl']);

        $this->actingAs($user)->get(route('dashboard'));

        $this->assertSame(
            '2 uur geleden',
            Datum::geleden(CarbonImmutable::create(2026, 9, 28, 10, 0)),
        );
    }

    public function test_the_security_log_shows_a_readable_moment(): void
    {
        $user = User::factory()->create(['locale' => 'nl']);
        $user->givePermissionTo(
            Permission::findOrCreate('manage portal', 'web')
        );

        $this->actingAs($user)
            ->get(route('admin.security.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/SecurityEvents'));

        // De vorm zelf is hierboven al getoetst; hier gaat het erom dat het
        // scherm de helper gebruikt en niet zijn eigen formaat verzint.
        $this->assertStringNotContainsString(
            'toDateTimeString',
            file_get_contents(app_path('Http/Controllers/Admin/SecurityEventController.php')),
        );
    }
}
