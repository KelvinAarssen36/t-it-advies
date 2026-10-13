<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De kop boven de tijdlijn: de titel en de zin eronder.
 *
 * Dit stond in het Vue-component, en daarmee kon de klant het niet
 * aanpassen terwijl de rest van dat onderdeel wél van hem is. Nu hoort het
 * bij de module, en wordt het samen met de cijfers bewerkt -- het is
 * tenslotte precies hetzelfde blok op zijn website.
 *
 * **Eén rij, en dat is geen tabel die groeit.** Er is één tijdlijn, dus
 * één kop. Een `key`-kolom zoals bij `experience_stats` zou suggereren dat
 * er meer bij kunnen komen; dat kan niet, en een tabel die liegt over wat
 * hij bevat is erger dan een tabel met één rij.
 *
 * **Het opschrift ("Ervaring") staat er niet bij.** Dat is de naam van het
 * onderdeel zelf: hij staat ook in het menu van de site en op het
 * indelingsscherm. Zou de klant hem hier kunnen wijzigen, dan heet
 * hetzelfde onderdeel op drie plekken anders.
 *
 * `machine_translated_at` werkt net als bij `experiences`: het zegt dat
 * het Engels van de vertaaldienst komt en nog door niemand is nagelezen.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_headings', function (Blueprint $table) {
            $table->id();

            /*
             * De titel is verplicht en de inleiding niet. Zonder titel
             * staat er een blok zonder kop op de website; zonder inleiding
             * staat er gewoon geen zin onder, en dat oogt prima.
             */
            $table->string('title_nl');
            $table->string('title_en')->nullable();
            $table->string('intro_nl', 300)->nullable();
            $table->string('intro_en', 300)->nullable();

            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_headings');
    }
};
