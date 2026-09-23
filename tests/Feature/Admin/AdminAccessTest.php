<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Het beveiligde gedeelte hangt volledig aan rechten. Deze tests bewaken dat
 * een gewone ingelogde gebruiker er niet in komt.
 */
class AdminAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_permissions_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('admin.dashboard'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.mail.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.security.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_an_admin_can_open_every_page(): void
    {
        $user = User::factory()->create();
        $user->assignRole('admin');

        $this->actingAs($user)->get(route('admin.dashboard'))->assertOk();
        $this->actingAs($user)->get(route('admin.mail.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.security.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.users.index'))->assertOk();
    }

    public function test_permissions_are_checked_per_page(): void
    {
        $user = User::factory()->create();
        $user->givePermissionTo('view mail log');

        $this->actingAs($user)->get(route('admin.mail.index'))->assertOk();
        $this->actingAs($user)->get(route('admin.security.index'))->assertForbidden();
        $this->actingAs($user)->get(route('admin.users.index'))->assertForbidden();
    }
}
