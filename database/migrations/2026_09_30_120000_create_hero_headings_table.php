<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De kop van de landingspagina: het eerste dat een bezoeker leest.
 *
 * Drie teksten, en ze stonden alle drie hardgecodeerd in HeroSection.vue:
 * het opschrift erboven ("IT-advies en realisatie"), de grote titel
 * eronder, en de zin daar weer onder. Daarmee kon de klant het enige
 * scherm dat iedereen ziet als enige niet aanpassen.
 *
 * **Eén rij, en dat is geen tabel die groeit.** Er is één landingspagina
 * en dus één kop. Zelfde afweging als bij `experience_headings`: een
 * `key`-kolom zou suggereren dat er meer bij kunnen komen, en een tabel
 * die liegt over wat hij bevat is erger dan een tabel met één rij.
 *
 * **Het opschrift staat er wél bij, anders dan bij de tijdlijn.** Daar is
 * "Ervaring" de naam van het onderdeel zelf -- die staat ook in het menu
 * en op het indelingsscherm, en die op drie plekken anders kunnen laten
 * heten is vragen om verwarring. Hier is het opschrift gewoon tekst: het
 * staat nergens anders, en op een telefoon is het bovendien de functie op
 * het visitekaartje onder de titel.
 *
 * `machine_translated_at` werkt net als bij `experiences`: het zegt dat
 * het Engels van de vertaaldienst komt en nog door niemand is nagelezen.
 *
 * Zie docs/architecture/modules/kop.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_headings', function (Blueprint $table) {
            $table->id();

            /*
             * Het opschrift en de titel zijn verplicht, de zin eronder
             * niet. Zonder titel staat er een pagina zonder kop; zonder
             * die laatste zin staat er gewoon niets, en dat oogt prima.
             *
             * De lengtes zijn krap met reden: dit is een kop en geen
             * alinea. De titel staat op een telefoon op vier regels als
             * hij te lang is, en het opschrift staat in kapitalen met
             * ruime letterafstand -- daar passen geen zestig tekens in.
             */
            $table->string('eyebrow_nl', 60);
            $table->string('eyebrow_en', 60)->nullable();
            $table->string('title_nl', 120);
            $table->string('title_en', 120)->nullable();
            $table->string('intro_nl', 300)->nullable();
            $table->string('intro_en', 300)->nullable();

            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_headings');
    }
};
