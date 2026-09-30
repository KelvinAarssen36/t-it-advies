<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Al deze seeders horen in elke omgeving te draaien, ook in productie.
     * Het zijn geen testdata maar de basis van de applicatie: zonder rollen
     * werkt geen enkele rechtencontrole, zonder het account van de eigenaar
     * kan niemand inloggen, zonder de secties is de landingspagina leeg, en
     * de loopbaan is de echte werkervaring van de eigenaar.
     *
     * Ze zijn allemaal aanvullend en niet terugzettend: opnieuw draaien mag
     * altijd en gooit niets weg wat de klant zelf heeft ingesteld.
     *
     * Komt er later wél testdata bij, zet die dan achter een controle op
     * `app()->environment('local')` -- zoals het beheerdersaccount met een
     * vast wachtwoord dat hier eerder stond.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            PortalAccountSeeder::class,
            PageSectionSeeder::class,
            ExperienceSeeder::class,
            ExperienceStatSeeder::class,
            ExperienceHeadingSeeder::class,
        ]);
    }
}
