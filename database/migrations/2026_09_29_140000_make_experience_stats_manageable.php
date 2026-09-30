<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * De cijfers boven de tijdlijn worden beheerbaar.
 *
 * Ze waren drie vaste rijen met alleen een getal: het woord eronder stond
 * in code en "leeg" betekende "reken het uit". De klant kon dus niet zeggen
 * hoe een cijfer heet, en ook niet dat hij er eentje níet wil.
 *
 * Wat erbij komt:
 *
 * - **`label_nl` en `label_en`** -- het woord onder het getal. Leeg
 *   betekent: gebruik het standaardwoord van dit soort. Zo blijft een
 *   cijfer dat de klant niet hernoemt vanzelf meegaan met de taal.
 * - **`modus`** -- automatisch, een eigen getal, of niet tonen. Dat is de
 *   kern van de wijziging: "leeg" betekende twee dingen tegelijk, en dat
 *   kan niet als de klant er ook eentje mee wil verbergen.
 * - **`position`** -- de volgorde waarin ze op de website staan.
 *
 * **De unieke sleutel op `key` gaat eraf.** Die kolom houdt nu het *soort*
 * cijfer bij, en van het soort `eigen` mogen er meer zijn -- dat is hoe de
 * klant aan een vierde cijfer komt dat wij niet kunnen uitrekenen.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('experience_stats', function (Blueprint $table) {
            $table->string('label_nl')->nullable()->after('key');
            $table->string('label_en')->nullable()->after('label_nl');
            $table->string('modus')->default('automatisch')->after('value');
            $table->unsignedInteger('position')->default(0)->after('modus');
        });

        /*
         * Bestaande rijen houden wat ze waren. Een rij met een getal stond
         * op "eigen getal", een lege op "automatisch" -- precies de twee
         * betekenissen die `value` tot nu toe in zijn eentje droeg.
         *
         * De volgorde komt uit de oude vaste reeks, zodat de website er na
         * deze migratie hetzelfde uitziet als ervoor.
         */
        $volgorde = ['jaren' => 0, 'functies' => 1, 'organisaties' => 2];

        foreach ($volgorde as $soort => $plek) {
            DB::table('experience_stats')
                ->where('key', $soort)
                ->update(['position' => $plek]);
        }

        DB::table('experience_stats')
            ->whereNotNull('value')
            ->update(['modus' => 'eigen']);

        Schema::table('experience_stats', function (Blueprint $table) {
            $table->dropUnique(['key']);
            $table->index('position');
        });
    }

    public function down(): void
    {
        Schema::table('experience_stats', function (Blueprint $table) {
            $table->dropIndex(['position']);
            $table->dropColumn(['label_nl', 'label_en', 'modus', 'position']);
        });

        /*
         * De unieke sleutel kan alleen terug als er geen dubbele soorten
         * meer staan. Eigen cijfers bestonden in de oude vorm niet, dus die
         * gaan weg -- er is geen kolom meer om ze in te bewaren.
         */
        DB::table('experience_stats')->where('key', 'eigen')->delete();

        Schema::table('experience_stats', function (Blueprint $table) {
            $table->unique('key');
        });
    }
};
