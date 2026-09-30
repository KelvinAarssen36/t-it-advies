<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De kop boven de diensten: het opschrift, de titel en de zin eronder.
 *
 * Eén rij, net als `hero_headings` en `experience_headings`.
 *
 * > **Dit is de derde bijna identieke koptabel, en dat is bekend.**
 * > Ze samenvoegen tot één `section_headings` met een regel per
 * > onderdeel is de betere oplossing: dan heeft elk toekomstig onderdeel
 * > gratis een beheerbare kop. Het is nu niet gedaan omdat het twee
 * > schermen raakt die werken, en de winst pas komt bij de vierde
 * > module. Dát is het afgesproken moment; het staat als open punt in
 * > docs/openstaand.md.
 *
 * `machine_translated_at` werkt net als elders: het zegt dat het Engels
 * van de vertaaldienst komt en nog door niemand is nagelezen.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_headings', function (Blueprint $table) {
            $table->id();

            /*
             * Het opschrift en de titel zijn verplicht, de zin eronder
             * niet. Zonder titel staat er een blok zonder kop; zonder
             * die zin staat er gewoon niets, en dat oogt prima.
             */
            $table->string('eyebrow_nl', 60);
            $table->string('eyebrow_en', 60)->nullable();
            $table->string('title_nl', 120);
            $table->string('title_en', 120)->nullable();
            $table->string('intro_nl', 300)->nullable();
            $table->string('intro_en', 300)->nullable();

            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_headings');
    }
};
