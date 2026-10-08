<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vier cijfers die je overtypt om een back-up terug te zetten.
 *
 * Eerst moest de **naam** worden overgetypt -- `2026-10-08-1130`. Dat is
 * een drempel, maar een onhandige: vijftien tekens met streepjes erin
 * overtypen is vooral vervelend, en vervelend is niet hetzelfde als
 * zorgvuldig. Wie geïrriteerd raakt, let minder op.
 *
 * Vier cijfers zijn kort genoeg om over te typen en lang genoeg om niet
 * per ongeluk te raken. **Cijfers en geen woord**, want een woord zou
 * Nederlands of Engels zijn en dan klopt het in één van de twee talen
 * niet.
 *
 * Het is geen geheim: de code staat gewoon in het venster. Hij is er om
 * een handeling bewust te maken, niet om iemand buiten te houden -- dat
 * doet de authenticator-code ernaast.
 *
 * Zie docs/operations/back-ups.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->string('bevestigcode', 4)->nullable()->after('naam');
        });

        // Bestaande back-ups krijgen er ook een; zonder code is er niets
        // over te typen en zou het venster vastlopen.
        foreach (DB::table('backups')->pluck('id') as $id) {
            DB::table('backups')
                ->where('id', $id)
                ->update(['bevestigcode' => (string) random_int(1000, 9999)]);
        }
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropColumn('bevestigcode');
        });
    }
};
