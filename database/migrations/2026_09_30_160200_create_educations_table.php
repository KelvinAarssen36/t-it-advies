<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De opleidingen onder de certificaten.
 *
 * **Bewust een stuk kaler dan `certificates`.** Dit is een lijstje van
 * twee of drie regels onder het raster, geen tweede etalage. Geen logo,
 * geen detailvenster, geen certificaatnummer -- en geen `position`.
 *
 * Die laatste is de opvallendste: overal elders in dit project bepaalt de
 * klant de volgorde met slepen. Hier niet, want bij twee of drie
 * opleidingen is "laatst afgeronde bovenaan" altijd de goede volgorde,
 * en dan is een sleepvenster gereedschap voor een probleem dat niet
 * bestaat. Zie `Education::scopeOpPeriode()`.
 *
 * Het staat in een eigen tabel en niet als soort binnen `certificates`,
 * omdat de velden echt verschillen: een certificaat heeft een
 * behaaldatum en een geldigheidsdatum, een opleiding een periode. Alles
 * in één tabel zou een rij kolommen opleveren die de helft van de tijd
 * leeg is, met validatie die per soort andersom werkt.
 *
 * Op de pagina zijn het wél één onderdeel: `PageSectionKey::Certificaten`
 * toont allebei. Zie docs/architecture/modules/certificaten.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('educations', function (Blueprint $table) {
            $table->id();

            $table->boolean('published')->default(true);

            // De opleiding of het diploma.
            $table->string('title_nl', 120);
            $table->string('title_en', 120)->nullable();

            // De school of hogeschool. Een eigennaam, dus niet vertaald
            // -- zelfde afweging als bij `certificates.issuer`.
            $table->string('institution', 120);

            // Bijvoorbeeld "MBO niveau 4". Kort, want het staat rechts
            // op één regel naast de periode.
            $table->string('level_nl', 60)->nullable();
            $table->string('level_en', 60)->nullable();

            /*
             * Op maandnauwkeurigheid, net als bij de certificaten en de
             * tijdlijn. Een lege `ended_on` betekent "loopt nog", en die
             * komt bovenaan te staan.
             */
            $table->date('started_on');
            $table->date('ended_on')->nullable();

            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('educations');
    }
};
