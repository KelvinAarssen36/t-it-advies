<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De indeling van de landingspagina: welk onderdeel staat waar, en staat
 * het aan.
 *
 * Twee dingen die je hier **niet** vindt, en dat is met opzet:
 *
 * - **De inhoud.** Die hoort bij het onderdeel zelf, in zijn eigen tabel.
 *   Deze tabel gaat alleen over de volgorde.
 * - **Of een onderdeel vastzit.** Dat de kop bovenaan hoort staat in
 *   App\Enums\PageSectionKey, dus in code. Een kolom zou betekenen dat een
 *   aangepast verzoek hem los kan wrikken.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('page_sections', function (Blueprint $table) {
            $table->id();

            /*
             * De sleutel uit PageSectionKey. Uniek, want een onderdeel kan
             * maar één keer op de pagina staan -- en zonder die grens levert
             * een dubbel geseede rij een pagina op die twee keer hetzelfde
             * toont, wat je pas in de browser ziet.
             */
            $table->string('key', 40)->unique();

            /*
             * Lager staat hoger op de pagina. Er zit ruimte tussen de
             * standaardwaarden (0, 100, 200, ...) zodat de vaste onderdelen
             * ver genoeg aan de uiteinden staan; bij het opslaan hernummert
             * de server de verplaatsbare onderdelen ertussenin.
             */
            $table->unsignedSmallInteger('position')->index();

            /*
             * Of de klant hem aan heeft staan. Staat los van "is er inhoud":
             * een onderdeel kan aanstaan en toch van de website verdwijnen
             * omdat er niets in staat. Die twee redenen zien er op het
             * indelingsscherm anders uit, want ze vragen om iets anders.
             */
            $table->boolean('visible')->default(true);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('page_sections');
    }
};
