<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Een onderwerp kan uitgelicht worden.
 *
 * De eigenaar wil soms één onderwerp naar voren halen -- "Storing of spoed"
 * bijvoorbeeld, of de dienst waar hij dat kwartaal op inzet. Dat is iets
 * anders dan de volgorde: bovenaan staan betekent "eerst", uitgelicht
 * betekent "kijk hier".
 *
 * **Een vlag en geen plek in de volgorde.** Het alternatief was het
 * onderwerp gewoon bovenaan slepen, maar dan verliest hij de volgorde die
 * hij bedacht had zodra hij iets anders wil uitlichten -- en terugzetten is
 * dan handwerk. Zo blijft de volgorde van hem en is uitlichten één vinkje
 * dat je net zo makkelijk weer uitzet.
 *
 * `false` als standaard: een nieuw onderwerp licht niets uit. Dat is de
 * stille kant, en uitlichten hoort een keuze te zijn.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contact_subjects', function (Blueprint $table) {
            $table->boolean('featured')->default(false)->after('published');
        });
    }

    public function down(): void
    {
        Schema::table('contact_subjects', function (Blueprint $table) {
            $table->dropColumn('featured');
        });
    }
};
