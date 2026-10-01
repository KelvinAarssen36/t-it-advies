<?php

namespace Tests\Feature\Settings;

use App\Enums\DashboardTimezone;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De instellingen van het dashboard.
 *
 * Nu één instelling -- de tijdzone van de klok -- maar met een eigen
 * gedeelte, omdat er meer bij komt. De eigenaar vroeg er in die vorm om.
 *
 * **Waar het hier echt om gaat is de standaardwaarde.** Die staat in de
 * enum en niet in het schema, dus een lege kolom moet overal Amsterdam
 * opleveren: op het dashboard, in de instellingen en in het model. Zou die
 * standaard op twee plekken staan, dan gaan ze op een dag uit elkaar lopen
 * en kijkt de eigenaar naar een klok die een uur mis staat zonder dat
 * iemand weet waarom.
 *
 * Zie docs/architecture/dashboard.md.
 */
class DashboardSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function gebruiker(): User
    {
        return User::factory()->create(['dashboard_timezone' => null]);
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('dashboard-settings.edit'))
            ->assertRedirect(route('login'));
    }

    public function test_the_screen_shows_the_current_zone_and_the_choices(): void
    {
        $this->actingAs($this->gebruiker())
            ->get(route('dashboard-settings.edit'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('settings/Dashboard')
                ->where('tijdzone', DashboardTimezone::Amsterdam->value)
                ->has('opties', count(DashboardTimezone::cases())));
    }

    /** Niets gekozen betekent Nederland, en niet "geen tijdzone". */
    public function test_without_a_choice_the_clock_runs_in_the_netherlands(): void
    {
        $gebruiker = $this->gebruiker();

        $this->assertSame(
            DashboardTimezone::Amsterdam,
            $gebruiker->dashboardTijdzone(),
        );

        $this->actingAs($gebruiker)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tijdzone', 'Europe/Amsterdam'));
    }

    public function test_another_zone_can_be_chosen(): void
    {
        $gebruiker = $this->gebruiker();

        $this->actingAs($gebruiker)
            ->patch(route('dashboard-settings.update'), [
                'tijdzone' => DashboardTimezone::Tokio->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(
            DashboardTimezone::Tokio,
            $gebruiker->fresh()?->dashboardTijdzone(),
        );
    }

    /** En die keuze komt door tot het dashboard. */
    public function test_the_chosen_zone_reaches_the_dashboard(): void
    {
        $gebruiker = User::factory()->create([
            'dashboard_timezone' => DashboardTimezone::NewYork,
        ]);

        $this->actingAs($gebruiker)
            ->get(route('dashboard'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('tijdzone', 'America/New_York'));
    }

    /**
     * Een zone die niet in de lijst staat wordt geweigerd.
     *
     * Ook al is het een geldige IANA-naam: de lijst is met opzet kort, en
     * wat er niet in staat kan het scherm ook niet tonen.
     */
    public function test_a_zone_outside_the_list_is_refused(): void
    {
        $gebruiker = $this->gebruiker();

        $this->actingAs($gebruiker)
            ->patch(route('dashboard-settings.update'), [
                'tijdzone' => 'Pacific/Auckland',
            ])
            ->assertSessionHasErrors('tijdzone');

        $this->assertNull($gebruiker->fresh()?->dashboard_timezone);
    }

    public function test_nonsense_is_refused(): void
    {
        $this->actingAs($this->gebruiker())
            ->patch(route('dashboard-settings.update'), ['tijdzone' => 'zomaar'])
            ->assertSessionHasErrors('tijdzone');
    }

    /** Opslaan zonder iets te wijzigen zegt dat, en doet niets. */
    public function test_saving_the_same_zone_changes_nothing(): void
    {
        $gebruiker = User::factory()->create([
            'dashboard_timezone' => DashboardTimezone::Londen,
        ]);

        $voor = $gebruiker->updated_at;

        $this->actingAs($gebruiker)
            ->patch(route('dashboard-settings.update'), [
                'tijdzone' => DashboardTimezone::Londen->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertEquals($voor, $gebruiker->fresh()?->updated_at);
    }

    /**
     * Elke zone in de lijst heeft een label en een werkende IANA-naam.
     *
     * Die tweede helft is het punt: een tikfout in `Asia/Tokio` in plaats
     * van `Asia/Tokyo` levert een lijst op die er goed uitziet en een klok
     * die omvalt. `DateTimeZone` weigert een naam die niet bestaat.
     */
    public function test_every_zone_is_real_and_has_a_label(): void
    {
        foreach (DashboardTimezone::cases() as $zone) {
            $this->assertNotSame('', $zone->label());

            // Valt om met een uitzondering als de naam niet bestaat.
            $this->assertSame(
                $zone->value,
                (new \DateTimeZone($zone->value))->getName(),
            );
        }
    }

    /** De lijst blijft kort; dat is het hele idee erachter. */
    public function test_the_list_stays_short_and_has_the_netherlands(): void
    {
        $waarden = array_column(DashboardTimezone::opties(), 'value');

        $this->assertContains('Europe/Amsterdam', $waarden);
        $this->assertLessThanOrEqual(8, count($waarden));
    }
}
