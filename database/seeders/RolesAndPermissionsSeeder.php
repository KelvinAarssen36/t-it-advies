<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * De rollen en rechten van de applicatie.
 *
 * Deze seeder is idempotent: hem nog eens draaien voegt alleen toe wat er
 * ontbreekt. Daardoor kun je hem ook bij een deploy draaien wanneer je een
 * recht toevoegt.
 *
 * Voeg je een recht toe, werk dan ook docs/security/rollen-en-rechten.md bij.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    public const PERMISSIONS = [
        'view mail log',
        'view security log',
        'view pulse',
        'manage users',
    ];

    /**
     * @var array<string, array<int, string>>
     */
    public const ROLES = [
        'admin' => self::PERMISSIONS,
        'developer' => [
            'view mail log',
            'view security log',
            'view pulse',
        ],
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISSIONS as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        foreach (self::ROLES as $role => $permissions) {
            Role::findOrCreate($role, 'web')->syncPermissions($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
