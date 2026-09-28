<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * De rol en het recht van de applicatie. Enkelvoud, en dat is de afspraak.
 *
 * Er is **één recht** en **één rol** die dat recht heeft. Het portaal heeft
 * één gebruiker -- de eigenaar van de website -- en wij gebruiken dezelfde
 * rol. Er is dus geen beheerder die wél bij het ene scherm mag en niet bij
 * het andere.
 *
 * Een nieuw beheerscherm komt achter `manage portal`. Merk je dat je een
 * tweede recht aan het bedenken bent, dan is dat het signaal om te stoppen:
 * dat maakt het alleen ingewikkelder zonder dat iemand er iets aan heeft, en
 * het levert schermen op die niet verschijnen omdat een recht ergens nog
 * niet is geseed.
 *
 * Deze seeder is idempotent én **gezaghebbend**: wat hier niet in staat
 * wordt uit de database verwijderd. Daardoor verdwijnt een recht dat je
 * weghaalt ook echt, in plaats van als wees te blijven hangen.
 *
 * Zie docs/security/rollen-en-rechten.md.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    public const PERMISSIONS = [
        'manage portal',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    public const ROLES = [
        'admin' => self::PERMISSIONS,
    ];

    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);

        $registrar->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        // Dit is de belangrijke, en hij is met bloed geschreven: bij de
        // eerste findOrCreate hierboven laadt de registrar de rechtenlijst
        // in het geheugen -- op een verse database is die dan nog leeg.
        // Zonder deze regel zoekt syncPermissions hieronder in die lege
        // lijst en faalt de seeder met "There is no permission named ...".
        //
        // Dat gebeurt alleen op een database waar de rechten nog niet in
        // staan, dus precies bij de allereerste deploy en nergens anders.
        $registrar->forgetCachedPermissions();

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        $this->ruimOp();

        $registrar->forgetCachedPermissions();
    }

    /**
     * Haalt weg wat niet meer in de lijsten hierboven staat.
     *
     * Zonder dit blijft een recht of een rol die je schrapt gewoon in de
     * database staan, inclusief de koppelingen naar gebruikers. Dat is geen
     * theoretisch probleem: je denkt dat je iets hebt weggehaald terwijl
     * iemand het nog heeft.
     */
    private function ruimOp(): void
    {
        Permission::query()
            ->whereNotIn('name', self::PERMISSIONS)
            ->get()
            ->each(fn (Permission $permission) => $permission->delete());

        Role::query()
            ->whereNotIn('name', array_keys(self::ROLES))
            ->get()
            ->each(fn (Role $role) => $role->delete());
    }
}
