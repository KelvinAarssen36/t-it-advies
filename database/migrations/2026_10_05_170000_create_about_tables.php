<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Het onderdeel "Over mij": het korte stuk en de aparte pagina.
 *
 * **Twee tabellen, en de verdeling is de hele module.** `about_settings`
 * is één rij met de teksten, `about_points` zijn de korte punten eronder --
 * die laatste moeten versleepbaar zijn en per regel te valideren, dus een
 * eigen tabel. Dezelfde afweging als bij de expertisepunten van een dienst;
 * zie service_points.
 *
 * In `about_settings` zitten twee groepen kolommen die niet door elkaar
 * mogen lopen, want dat was de zorg van de eigenaar: wat komt er op de
 * voorpagina en wat alleen op de aparte pagina?
 *
 *     summary_*                 → het korte stuk, altijd op de voorpagina
 *     page_enabled              → of de aparte pagina bestaat
 *     page_title_*, page_intro_*, story_*, photo_path → alleen die pagina
 *
 * Het beheerscherm zet ze daarom onder twee koppen, en het tweede blok
 * vergrijst zolang `page_enabled` uit staat. Zie resources/js/pages/
 * website/OverMij.vue.
 *
 * **Eén rij, net als contact_settings.** Geen `key`-kolom en geen enum:
 * er is één eigenaar en één "Over mij". `AboutSetting::huidige()` haalt hem
 * op en geeft een niet-opgeslagen model terug zolang hij niet bestaat.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('about_settings', function (Blueprint $table) {
            $table->id();

            /*
             * Het korte stuk op de voorpagina.
             *
             * Vierhonderd tekens, en die grens is er met opzet. Zonder
             * limiet wordt dit blok het hele levensverhaal midden op de
             * voorpagina, en dan is de aparte pagina er voor niets. Het
             * beheerscherm laat een teller meelopen; de grens staat in
             * AboutRequest zodat die twee niet uiteen kunnen lopen.
             */
            $table->string('summary_nl', 400)->nullable();
            $table->string('summary_en', 400)->nullable();

            /*
             * Of de aparte pagina bestaat.
             *
             * Staat hij uit, dan geeft `/over-mij` een 404 en staat er op
             * de voorpagina geen knop ernaartoe. Dezelfde opzet als de
             * keuze tussen een contactformulier op de pagina of een eigen
             * contactpagina; zie ContactWeergave.
             */
            $table->boolean('page_enabled')->default(false);

            /*
             * De kop van die pagina, los van de kop boven het blok.
             *
             * Een pagina met dezelfde titel als het blok waar je vandaan
             * klikt leest alsof je niet bent verdergegaan. De kop boven het
             * blók staat in `section_headings`, zoals bij elke sectie; deze
             * hoort bij de pagina en dus hier.
             */
            $table->string('page_title_nl', 120)->nullable();
            $table->string('page_title_en', 120)->nullable();
            $table->string('page_intro_nl', 300)->nullable();
            $table->string('page_intro_en', 300)->nullable();

            /*
             * Het verhaal: `text`, want hier mogen witregels in staan. Die
             * worden op de pagina alinea's, net als bij een dienst. Geen
             * opmaakbalkje, dus er kan ook niets scheef staan.
             */
            $table->text('story_nl')->nullable();
            $table->text('story_en')->nullable();

            /*
             * De eigen foto, als de eigenaar er een heeft gekozen.
             *
             * `null` betekent "gebruik het medaillon" -- het portret dat al
             * in de hero staat. Dat is de standaard en niet een terugval
             * bij een fout: een eigen foto is de uitzondering, niet de
             * regel. Zie App\Models\AboutSetting::foto().
             */
            $table->string('photo_path')->nullable();

            $table->timestamp('machine_translated_at')->nullable();

            $table->timestamps();
        });

        Schema::create('about_points', function (Blueprint $table) {
            $table->id();

            $table->unsignedInteger('position')->default(0);

            /*
             * Tachtig tekens. Dit is een punt en geen zin: past het er niet
             * in, dan hoort het in het verhaal. Iets ruimer dan de zestig
             * van een expertisepunt, want die staan als labels naast elkaar
             * op een kaart en deze staan onder elkaar op een pagina.
             *
             * Het Engels mag leeg blijven en valt **níet** terug op het
             * Nederlands: een rijtje met drie Engelse en twee Nederlandse
             * punten is slordiger dan een rijtje van drie. Zelfde keuze als
             * bij een dienst, en met opzet anders dan bij een vraag in de
             * FAQ -- daar is de vertaling de helft van het enige dat er
             * staat. Zie App\Models\AboutPoint.
             */
            $table->string('text_nl', 80);
            $table->string('text_en', 80)->nullable();

            $table->timestamps();

            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('about_points');
        Schema::dropIfExists('about_settings');
    }
};
