<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De knoptekst van het LinkedIn-blok.
 *
 * Het opschrift, de titel en de zin erboven staan niet hier maar in
 * `section_headings`, net als bij elk ander onderdeel. Wat overblijft is
 * de tekst op de knop; die heeft geen plek in de gedeelde kop, dus krijgt
 * hij een eigen rij.
 *
 * **Geen kolom voor het adres.** De link ligt vast in config/site.php en
 * wordt gedeeld als Inertia-prop; een tweede plek om hem in te vullen zou
 * twee waarheden opleveren. Zie docs/architecture/modules/linkedin.md.
 *
 * **Beide kolommen nullable, en er wordt niets in gezet.** Leeg betekent
 * "de standaardtekst": "Bekijk [naam]" in die taal. Zo blijft de tekst die
 * er stond de standaard, en hoeft een bestaande site na deze migratie niets
 * te doen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('linkedin_settings', function (Blueprint $table) {
            $table->id();

            $table->string('button_nl', 40)->nullable();
            $table->string('button_en', 40)->nullable();

            // Of het Engels van de vertaaldienst komt en nog niet is nagelezen.
            $table->timestamp('button_translated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('linkedin_settings');
    }
};
