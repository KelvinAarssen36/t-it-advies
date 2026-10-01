<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De instellingen van het dashboard, bij de gebruiker.
 *
 * **Waarom op `users` en niet in een eigen tabel.** Dit is een persoonlijke
 * voorkeur, net als `locale` en het thema: welke tijd jíj op jouw dashboard
 * wil zien. Een eigen tabel met sleutel-waarderijen zou flexibeler zijn,
 * maar levert ongetypeerde tekst op waar de applicatie elke keer opnieuw
 * over moet nadenken. Komt er een tweede dashboardinstelling, dan is dat
 * een kolom erbij.
 *
 * `nullable` en geen standaardwaarde in het schema: de standaardzone staat
 * in `DashboardTimezone::STANDAARD`, en dat hoort één plek te zijn. Een
 * database die zelf 'Europe/Amsterdam' invult is een tweede bron die stil
 * uit de pas kan gaan lopen met de code.
 *
 * Zie docs/architecture/dashboard.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('dashboard_timezone', 64)
                ->nullable()
                ->after('locale');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('dashboard_timezone');
        });
    }
};
