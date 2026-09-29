<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De cijfers boven de tijdlijn, voor zover de klant ze zelf invult.
 *
 * Eén rij per cijfer, met de sleutel uit ExperienceStatKey. Wélke cijfers
 * er bestaan staat dus in code en niet hier -- dezelfde afspraak als bij
 * `page_sections`.
 *
 * **`value` mag leeg zijn, en dat is de normale toestand.** Leeg betekent
 * "reken het uit op basis van de tijdlijn". Dat is geen ontbrekende waarde
 * maar een betekenisvolle: zolang hij leeg is, klopt het cijfer vanzelf
 * zodra er een functie bij komt.
 *
 * Er komt geen kolom voor het label. Dat woord onder het getal -- "jaar
 * ervaring" -- is interfacetekst en hoort bij de vertalingen, niet bij de
 * inhoud die de klant beheert. Anders staat er Nederlands op de Engelse
 * site.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('experience_stats', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->unsignedInteger('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('experience_stats');
    }
};
