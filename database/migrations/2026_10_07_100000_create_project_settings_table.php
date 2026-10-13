<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De instellingen van de module Projecten, in één rij.
 *
 * Nu één kolom: hoe de pagina met alle projecten eruitziet.
 *
 * **Een eigen tabel en niet `site_settings`.** Die tabel is er voor wat
 * over de hele site gaat -- de mailstijl geldt ook voor een
 * beveiligingsmelding die niets met de website te maken heeft. Dit gaat
 * over één onderdeel, en een instelling van één module in een tabel die
 * "site" heet is het soort ding waar je een jaar later op de verkeerde
 * plek naar zoekt. Hetzelfde argument dat `site_settings` gebruikt om niet
 * in `contact_settings` te gaan zitten, maar dan andersom.
 *
 * Eén rij, zoals `contact_settings` en `site_settings`.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_settings', function (Blueprint $table) {
            $table->id();

            /*
             * `lijst` als standaard: dat is de vorm die het makkelijkst te
             * lezen is en die compact blijft, ook als er twintig projecten
             * staan. Zie App\Enums\ProjectWeergave voor de afweging.
             */
            $table->string('layout', 16)->default('lijst');

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('project_settings');
    }
};
