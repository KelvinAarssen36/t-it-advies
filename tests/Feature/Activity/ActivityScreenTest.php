<?php

namespace Tests\Feature\Activity;

use App\Models\ActivityEntry;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het scherm met het activiteitenlogboek.
 *
 * Het staat achter hetzelfde recht als de rest van het beheergedeelte.
 * Er is één recht en één rol; zie docs/security/rollen-en-rechten.md.
 */
class ActivityScreenTest extends TestCase
{
    use RefreshDatabase;

    private function beheerder(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        return $user;
    }

    public function test_a_guest_is_sent_to_the_login_screen(): void
    {
        $this->get(route('admin.activity.index'))->assertRedirect(route('login'));
    }

    public function test_the_right_is_needed(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs(User::factory()->create())
            ->get(route('admin.activity.index'))
            ->assertForbidden();
    }

    public function test_the_log_is_shown(): void
    {
        $user = $this->beheerder();

        $this->actingAs($user)
            ->get(route('admin.activity.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Activity')
                ->has('entries.data')
                ->has('actions', 3));
    }

    public function test_filtering_by_action_works(): void
    {
        $user = $this->beheerder();

        // De beheerder aanmaken heeft al een 'created' opgeleverd; deze
        // wijziging komt erbij als 'updated'.
        $this->actingAs($user);
        $user->update(['name' => 'Iets anders']);

        $this->actingAs($user)
            ->get(route('admin.activity.index', ['action' => 'updated']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('filters.action', 'updated')
                ->has('entries.data', 1));
    }

    public function test_the_subject_filter_grows_with_the_log(): void
    {
        // De lijst komt uit de tabel en niet uit een lijst die iemand moet
        // bijhouden. Is er nog niets, dan is het filter leeg in plaats van
        // een lijst met onderdelen die niet bestaan.
        $this->seed(RolesAndPermissionsSeeder::class);

        $user = User::factory()->create();
        $user->assignRole('admin');

        ActivityEntry::query()->delete();

        $this->actingAs($user)
            ->get(route('admin.activity.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('subjects', 0));
    }
}
