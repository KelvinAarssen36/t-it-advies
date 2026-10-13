<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Wanneer de inhoud is vastgelegd -- niet wanneer de rij is gemaakt.
 *
 * **Die twee zijn niet hetzelfde, en dat ging mis.** Een geüpload
 * bestand kreeg `created_at = nu`, dus een back-up van januari die je
 * vandaag terugplaatst gold als de nieuwste. De naam zei januari, de
 * volgorde zei vandaag, en het merkje "Nieuwste" stond op het verkeerde
 * bestand.
 *
 * Vanaf nu staat de echte momentopname in een eigen kolom, en daar wordt
 * op gesorteerd. Voor een verse back-up is dat gewoon "nu"; voor een
 * geüploade komt hij uit het manifest in het bestand zelf.
 *
 * Zie docs/operations/back-ups.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->timestamp('vastgelegd_op')->nullable()->after('naam');
            $table->index('vastgelegd_op');
        });

        /*
         * Bestaande rijen: de echte datum staat in het manifest van hun
         * eigen bestand. Lukt dat niet -- bestand weg, of onleesbaar --
         * dan is `created_at` de beste schatting die er is.
         */
        foreach (DB::table('backups')->get() as $rij) {
            $vastgelegd = $rij->created_at;

            $pad = Storage::disk('local')->path((string) $rij->bestand);

            if (is_file($pad) && class_exists(ZipArchive::class)) {
                $zip = new ZipArchive;

                if ($zip->open($pad, ZipArchive::RDONLY) === true) {
                    $ruw = $zip->getFromName('manifest.json');
                    $zip->close();

                    $manifest = is_string($ruw) ? json_decode($ruw, true) : null;

                    if (is_array($manifest) && is_string($manifest['gemaakt'] ?? null)) {
                        try {
                            $vastgelegd = Carbon::parse($manifest['gemaakt'])->toDateTimeString();
                        } catch (Throwable) {
                            // De schatting hierboven blijft staan.
                        }
                    }
                }
            }

            DB::table('backups')
                ->where('id', $rij->id)
                ->update(['vastgelegd_op' => $vastgelegd]);
        }
    }

    public function down(): void
    {
        Schema::table('backups', function (Blueprint $table) {
            $table->dropIndex(['vastgelegd_op']);
            $table->dropColumn('vastgelegd_op');
        });
    }
};
