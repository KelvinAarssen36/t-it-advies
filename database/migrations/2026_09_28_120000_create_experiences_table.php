<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De ervaringen op de tijdlijn: functie, organisatie, periode.
 *
 * Drie dingen die hier opvallen en uitleg verdienen.
 *
 * **Twee kolommen per vertaalbaar veld** (`_nl` en `_en`) in plaats van een
 * JSON-kolom of een aparte vertaaltabel. Saai en expres: je kunt er gewoon
 * op valideren, op zoeken en op sorteren, en het formulier met twee stappen
 * volgt er vanzelf uit. Zie docs/architecture/vertalingen.md.
 *
 * **De Engelse kolommen mogen leeg zijn.** Een item dat nog niet vertaald
 * is, hoort te bestaan -- de website valt dan terug op het Nederlands voor
 * wat verplicht is, en laat weg wat optioneel is.
 *
 * **De organisatie heeft géén tweede kolom.** Een bedrijfsnaam is een
 * eigennaam. "Van der Valk" wordt in het Engels niet "Of the Falcon", en
 * een vertaalveld ernaast nodigt alleen maar uit tot die fout.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experiences', function (Blueprint $table) {
            $table->id();

            // Een sleutel uit App\Enums\ExperienceIcon, geen naam van een
            // pictogram uit de bibliotheek. Zie de toelichting daar.
            $table->string('icon', 32)->default('werk');

            $table->string('role_nl', 120);
            $table->string('role_en', 120)->nullable();

            $table->string('organisation', 120);
            $table->string('organisation_url', 255)->nullable();

            // Sleutels uit EmploymentType en WorkplaceType. Allebei
            // optioneel: niet elke ervaring is een dienstverband.
            $table->string('employment', 32)->nullable();
            $table->string('workplace', 32)->nullable();

            $table->string('location_nl', 120)->nullable();
            $table->string('location_en', 120)->nullable();

            /*
             * Alleen de maand en het jaar doen ertoe; de dag staat altijd op
             * 1 en betekent niets. Een echte datumkolom is hier toch
             * handiger dan twee getallen: sorteren, vergelijken en
             * opmaken werken dan gewoon.
             *
             * `ended_on` leeg betekent "tot heden". Dat is geen ontbrekende
             * waarde maar een betekenisvolle: het is de ervaring die nu nog
             * loopt, en die staat bovenaan.
             */
            $table->date('started_on');
            $table->date('ended_on')->nullable();

            $table->text('description_nl')->nullable();
            $table->text('description_en')->nullable();

            /*
             * Wanneer de Engelse tekst door de vertaaldienst is gemaakt in
             * plaats van door de klant zelf. Leeg betekent "met de hand",
             * en het overzicht toont er een merkje bij zodat hij weet wat
             * hij nog moet nalopen. Zie docs/architecture/automatisch-vertalen.md.
             */
            $table->timestamp('machine_translated_at')->nullable();

            $table->timestamps();

            // De tijdlijn wordt altijd op periode gesorteerd.
            $table->index(['started_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experiences');
    }
};
