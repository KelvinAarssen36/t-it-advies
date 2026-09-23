<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * De rollen en rechten worden altijd geseed, ook in productie: dat is
     * geen testdata maar configuratie. Het beheerdersaccount maken we alleen
     * lokaal aan, want een vast wachtwoord hoort nergens anders thuis.
     */
    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);

        if (! app()->environment('local')) {
            return;
        }

        User::factory()
            ->create([
                'name' => 'Beheerder',
                'email' => 'admin@t-it-advies.test',
            ])
            ->assignRole('admin');
    }
}
