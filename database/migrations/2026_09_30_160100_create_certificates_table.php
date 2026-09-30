<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De certificaten op de landingspagina.
 *
 * De tijdlijn vertelt wát de eigenaar heeft gedaan; dit is het bewijs
 * erbij. Voor een IT-adviseur is dat geen detail: een bezoeker die een
 * logo van Microsoft of Cisco herkent is in één blik overtuigd van iets
 * waar drie alinea's tekst niet tegenop kunnen.
 *
 * **De kolommen heten `title` en `body`, net als bij een dienst.** Dat
 * is geen luiheid maar hergebruik: `TranslateController` kent die
 * veldnamen al, dus de vertaalknop werkt hier zonder dat er iets bij
 * hoeft.
 *
 * **`issuer` wordt niet vertaald.** "Microsoft" is een eigennaam, net
 * als `experiences.organisation`. Een veld dat in beide talen hetzelfde
 * is hoor je niet twee keer te laten invullen.
 *
 * **Twee datums, allebei op maandnauwkeurigheid.** Je haalt een
 * certificaat in maart 2024; de dag erbij zetten suggereert een precisie
 * die niemand nodig heeft en die de klant moet opzoeken. Ze staan als
 * `date` in de database omdat MySQL geen "maand" kent; de eerste van de
 * maand is de afspraak. Zie App\Support\Datum.
 *
 * **`expires_on` haalt een certificaat niet van de site.** Is de datum
 * voorbij, dan blijft hij gewoon staan -- behaald is behaald.
 * Automatisch verdwijnen zou betekenen dat de voorpagina verandert
 * zonder dat de eigenaar iets deed.
 *
 * De bezoeker ziet ook niet dát het is verlopen; dat is iets voor het
 * beheerscherm. Zie het model.
 *
 * Geen kolom voor een verificatielink: dat is bewust overgeslagen. Het
 * certificaatnummer staat er wel. Zie docs/architecture/modules/certificaten.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificates', function (Blueprint $table) {
            $table->id();

            $table->boolean('published')->default(true);

            /*
             * De volgorde is redactioneel, net als bij de diensten en
             * anders dan bij de tijdlijn: welk certificaat je vooraan
             * zet is een keuze, geen datum. De klant verzet hem met
             * slepen.
             */
            $table->unsignedInteger('position')->default(0);

            /*
             * Het logo van de uitgever. Dezelfde opslag als bij de
             * ervaringen -- een vierkant van 256 bij 256 op de schijf
             * `public` -- dus dezelfde uitsnijmachinerie werkt hier.
             * Staat er geen, dan valt de tegel terug op een pictogram.
             */
            $table->string('logo_path')->nullable();

            $table->string('title_nl', 120);
            $table->string('title_en', 120)->nullable();

            $table->string('issuer', 120);

            $table->date('issued_on');
            $table->date('expires_on')->nullable();

            $table->string('credential_id', 120)->nullable();

            // Kort gehouden in het scherm maar ruim in de kolom: dit is
            // de tekst in het detailvenster en niet op de tegel.
            $table->text('body_nl')->nullable();
            $table->text('body_en')->nullable();

            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();

            // Op volgorde ophalen is wat de website en het beheerscherm
            // allebei doen, dus die index verdient zich terug.
            $table->index(['position', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificates');
    }
};
