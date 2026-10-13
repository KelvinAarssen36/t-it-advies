<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Eén merkje "automatisch vertaald" per vertaalbaar veld in de voet.
 *
 * **Hier stond één kolom voor twee teksten, en dat ging stil mis.** De voet
 * heeft twee vertaalbare velden die in twee verschillende vensters worden
 * bewerkt: de zin onder het merk en het werkgebied. Allebei die vensters
 * lazen dezelfde `machine_translated_at` en schreven er hun eigen oordeel
 * in terug. Het gevolg:
 *
 * - Liet de eigenaar zijn tagline automatisch vertalen en typte hij daarna
 *   zijn werkgebied zelf, dan zette het contactvenster de kolom op `null`.
 *   **Het merkje bij de tagline was weg terwijl die tekst nog steeds
 *   onnagekeken machinevertaling was.**
 * - Andersom toonde het scherm de waarschuwing bij het blok met de tekst
 *   die hij juist wél zelf had geschreven.
 *
 * Een merkje dat de verkeerde kant op wijst is erger dan geen merkje: het
 * zegt "dit is nagekeken" over iets wat dat niet is.
 *
 * **De oude waarde gaat naar allebei de kolommen.** Dat is met opzet aan de
 * voorzichtige kant: de oude kolom zei alleen "er staat hier ergens
 * onnagekeken Engels" en niet wáár. Te veel merkjes betekent dat de
 * eigenaar één keer te vaak nakijkt; te weinig betekent dat hij denkt dat
 * iets is nagekeken terwijl dat niet zo is.
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('footer_settings', function (Blueprint $table) {
            $table->timestamp('tagline_translated_at')->nullable()->after('tagline_en');
            $table->timestamp('region_translated_at')->nullable()->after('region_en');
        });

        /*
         * Overzetten vóór het weggooien, anders is de informatie weg. Zie
         * de toelichting hierboven voor waarom hij naar allebei gaat.
         */
        DB::table('footer_settings')->update([
            'tagline_translated_at' => DB::raw('machine_translated_at'),
            'region_translated_at' => DB::raw('machine_translated_at'),
        ]);

        Schema::table('footer_settings', function (Blueprint $table) {
            $table->dropColumn('machine_translated_at');
        });
    }

    public function down(): void
    {
        Schema::table('footer_settings', function (Blueprint $table) {
            $table->timestamp('machine_translated_at')->nullable()->after('vat');
        });

        // Terug naar één kolom: de tagline wint, want dat was het veld dat
        // er bij het splitsen als eerste was.
        DB::table('footer_settings')->update([
            'machine_translated_at' => DB::raw('tagline_translated_at'),
        ]);

        Schema::table('footer_settings', function (Blueprint $table) {
            $table->dropColumn(['tagline_translated_at', 'region_translated_at']);
        });
    }
};
