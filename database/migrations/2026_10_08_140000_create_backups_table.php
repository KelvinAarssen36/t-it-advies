<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De lijst met back-ups van de website-inhoud.
 *
 * Alleen de administratie; het bestand zelf staat in
 * `storage/app/private/backups`. Dat is met opzet buiten de webroot: een
 * back-up die je met een geraden adres kunt downloaden is geen back-up
 * maar een lek.
 *
 * Zie docs/operations/back-ups.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();

            /*
             * De naam die de eigenaar ziet, en tegelijk de naam die hij
             * moet overtypen om terug te zetten. Uniek, want anders vraagt
             * dat overtypen niets.
             */
            $table->string('naam', 60)->unique();

            // handmatig | automatisch | geupload
            $table->string('soort', 20)->index();

            $table->string('bestand');
            $table->unsignedBigInteger('grootte')->default(0);

            /*
             * De SHA-256 van `data.json`. Hiermee wordt bij elk lezen
             * vastgesteld dat er niets is veranderd of beschadigd.
             */
            $table->string('checksum', 64);

            // Het aantal rijen per tabel, voor het verschil op het scherm.
            $table->json('aantallen');

            /*
             * De laatst gedraaide migratie op het moment van maken. Zo is
             * achteraf te zien of een back-up nog bij het huidige schema
             * past; zie Verschilrapport.
             */
            $table->string('schema_merk')->nullable();

            /*
             * Een vingerafdruk van déze site. Een back-up van een andere
             * installatie terugzetten is bijna altijd een vergissing, en
             * zonder dit merk is dat niet te zien.
             */
            $table->string('site_merk', 64)->nullable();

            /*
             * Vastgezet: telt niet mee in het maximum en wordt nooit
             * automatisch opgeruimd. Zo overleeft de goede stand van vlak
             * voor een misser de vijf back-ups die erna komen.
             */
            $table->boolean('vastgezet')->default(false);

            /*
             * Wanneer de teruglees-test voor het laatst is gelukt. Een
             * back-up die nooit is teruggelezen is een aanname; zie
             * BackupLezer en de knop "Controleren".
             */
            $table->timestamp('gecontroleerd_op')->nullable();

            /*
             * Wanneer hij voor het laatst is gedownload. Zolang dit leeg
             * is staat de back-up alleen op dezelfde server als de
             * website, en dat is precies wat de 3-2-1-regel niet wil.
             */
            $table->timestamp('gedownload_op')->nullable();

            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
