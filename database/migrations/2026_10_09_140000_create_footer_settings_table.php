<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Wat de eigenaar zelf in de voet van zijn site zet. Eén rij.
 *
 * Dezelfde opzet als `site_settings`, `about_settings` en
 * `contact_settings`: één rij, en `FooterSetting::huidige()` geeft een
 * niet-opgeslagen exemplaar zolang die rij er niet is.
 *
 * **Alles mag leeg.** Dat is hier geen slordigheid maar het ontwerp: elk
 * veld dat leeg blijft, verdwijnt van de site. Geen telefoonnummer
 * betekent geen lege regel met een icoontje ernaast, maar niets.
 *
 * **Wat hier níet in staat, en waarom niet:**
 *
 * | Wat              | Waar het wel staat         | Waarom daar                       |
 * | ---------------- | -------------------------- | --------------------------------- |
 * | Het e-mailadres  | `config/site.php`          | `config/mail.php` valt erop terug voor de ontvanger van het contactformulier; beheerbaar maken betekent dat de eigenaar zijn eigen formulier kan omleiden zonder het door te hebben |
 * | De LinkedIn-link | `config/site.php`          | Eén link die niet verandert       |
 * | Het jaartal      | De browser                 | Telt zichzelf                     |
 * | De sitemap       | `page_sections`            | Volgt de indeling van de eigenaar |
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('footer_settings', function (Blueprint $table) {
            $table->id();

            /*
             * De zin onder het merk. Honderdtwintig tekens is ruim één
             * regel; daarboven wordt het een alinea, en een alinea in een
             * voet leest niemand.
             */
            $table->string('tagline_nl', 120)->nullable();
            $table->string('tagline_en', 120)->nullable();

            /*
             * Vrije tekst en geen cijferkolom: "06 12 34 56 78" en
             * "+31 6 12345678" zijn allebei geldig, en de eigenaar hoort
             * zelf te kiezen hoe zijn nummer eruitziet. De `tel:`-link
             * strippen we in de browser.
             */
            $table->string('phone', 32)->nullable();

            /*
             * Plaats of werkgebied, en dus vertaalbaar: "Noord-Brabant en
             * omstreken" bevat Nederlandse woorden. Het nummer eronder
             * niet -- dat is in beide talen hetzelfde.
             */
            $table->string('region_nl', 60)->nullable();
            $table->string('region_en', 60)->nullable();

            $table->string('kvk', 20)->nullable();
            $table->string('vat', 20)->nullable();

            $table->timestamp('machine_translated_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('footer_settings');
    }
};
