<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Twee dingen die de klant per ervaring wilde kunnen doen: hem online of
 * offline zetten, en er een eigen logo bij uploaden.
 *
 * **`published` staat standaard aan.** Alles wat er al stond, stond al op
 * de website; een migratie hoort de site niet leeg te maken. Nieuwe
 * ervaringen kiezen het zelf in het formulier.
 *
 * Er komt geen index op `published`. Een loopbaan is een lijst van
 * tientallen regels, en de database leest die tabel sneller helemaal dan
 * via een index op een kolom met twee mogelijke waarden.
 *
 * **`logo_path` is een pad en geen URL.** Wat er in de database staat is
 * `ervaring/abc123.webp`; waar dat vandaan komt bepaalt de schijf in
 * config/filesystems.php. Zou er een volledige URL staan, dan moet je de
 * hele tabel bijwerken zodra het domein verandert of de bestanden naar een
 * andere opslag verhuizen.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->boolean('published')->default(true)->after('icon');
            $table->string('logo_path')->nullable()->after('published');
        });
    }

    public function down(): void
    {
        Schema::table('experiences', function (Blueprint $table) {
            $table->dropColumn(['published', 'logo_path']);
        });
    }
};
