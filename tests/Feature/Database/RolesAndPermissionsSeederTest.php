<?php

namespace Tests\Feature\Database;

use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * De rollen en rechten zijn geen testdata: zonder deze seeder werkt geen
 * enkele `can:`-controle en komt niemand het beheergedeelte in.
 *
 * Deze test kijkt naar de uitkomst en niet naar het aantal regels: dat de
 * rollen daadwerkelijk hun rechten hébben. Dat klinkt vanzelfsprekend, maar
 * de seeder heeft ooit stilletjes rollen zonder rechten opgeleverd doordat
 * de rechtencache van spatie nog de lege lijst van een verse database
 * vasthield.
 */
class RolesAndPermissionsSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_role_gets_its_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        foreach (RolesAndPermissionsSeeder::ROLES as $naam => $rechten) {
            $rol = Role::findByName($naam, 'web');

            $this->assertSame(
                collect($rechten)->sort()->values()->all(),
                $rol->permissions->pluck('name')->sort()->values()->all(),
                "De rol {$naam} heeft niet de rechten die hij hoort te hebben.",
            );
        }
    }

    public function test_running_it_again_changes_nothing(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        // Idempotent, want hij draait bij elke deploy mee.
        $this->assertCount(
            count(RolesAndPermissionsSeeder::PERMISSIONS),
            Role::findByName('admin', 'web')->permissions,
        );

        $this->assertSame(
            count(RolesAndPermissionsSeeder::ROLES),
            Role::query()->count(),
        );
    }

    public function test_what_is_no_longer_in_the_list_is_removed(): void
    {
        /*
         * De seeder is gezaghebbend en niet alleen aanvullend. Zonder dat
         * blijft een recht dat je schrapt gewoon staan, inclusief de
         * koppeling naar de gebruiker die het had -- en dan denk je dat je
         * iets hebt weggehaald terwijl iemand het nog heeft.
         *
         * Dit project heeft één recht en één rol; zie
         * docs/security/rollen-en-rechten.md.
         */
        Permission::findOrCreate('oud recht', 'web');
        Role::findOrCreate('oude rol', 'web');

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertNull(Permission::query()->where('name', 'oud recht')->first());
        $this->assertNull(Role::query()->where('name', 'oude rol')->first());

        // En wat er wél hoort te staan, staat er nog.
        $this->assertNotNull(Role::findByName('admin', 'web'));
    }
}
