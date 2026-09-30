<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De diensten op de landingspagina.
 *
 * Ze stonden met z'n drieën hardgecodeerd in DienstenSection.vue. Nu
 * bepaalt de klant hoeveel het er zijn, wat erin staat en in welke
 * volgorde.
 *
 * **Geen maximum**, anders dan bij de cijfers boven de tijdlijn. Daar is
 * vier een ontwerpgrens omdat vijf getallen naast elkaar geen
 * samenvatting meer zijn; hier is het een raster dat meegroeit. Het
 * beheerscherm zegt wel welke aantallen het mooist uitkomen.
 *
 * **Drie soorten tekst, en dat is een keuze over het scherm en niet over
 * de database.** `summary` staat op de kaart en is verplicht -- een kaart
 * met alleen een titel is een lege kaart. `body` is het hele verhaal en
 * staat in het venster dat opengaat als je op de kaart klikt; is het
 * leeg, dan is de kaart gewoon niet aanklikbaar. Zo bepaalt de klant per
 * dienst of er meer te vertellen valt.
 *
 * De expertisepunten staan in `service_points`, en de kop boven het hele
 * blok in `service_headings`.
 *
 * `machine_translated_at` werkt net als bij `experiences`: het zegt dat
 * het Engels van de vertaaldienst komt en nog door niemand is nagelezen.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->id();

            // De sleutel uit App\Enums\ServiceIcon, en niet de naam van
            // een pictogram uit de bibliotheek. Zie die enum.
            $table->string('icon', 32)->default('advies');

            $table->boolean('published')->default(true);

            /*
             * De volgorde is hier wél redactioneel, anders dan bij een
             * ervaring. Een tijdlijn ordent zichzelf op datum; welke
             * dienst je het eerst wilt noemen is een keuze. Vandaar een
             * echte kolom die de klant met slepen verzet.
             */
            $table->unsignedInteger('position')->default(0);

            $table->string('title_nl', 80);
            $table->string('title_en', 80)->nullable();

            // Kort, want dit staat op een kaart naast twee andere. Drie
            // regels is wat er past; driehonderd tekens is daar ruim voor.
            $table->string('summary_nl', 300);
            $table->string('summary_en', 300)->nullable();

            $table->text('body_nl')->nullable();
            $table->text('body_en')->nullable();

            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();

            /*
             * Op volgorde ophalen is wat de website en het beheerscherm
             * allebei doen, dus die index verdient zich terug. Op
             * `published` staat er bewust geen: twee waarden in een
             * tabel van een handvol rijen.
             */
            $table->index(['position', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
