<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De expertisepunten onder een dienst.
 *
 * Korte labels die zeggen wat er onder een dienst valt: bij Beheer
 * bijvoorbeeld "monitoring", "back-ups", "updates". Op de website staan
 * ze als kleine labels onder de tekst van de kaart, en ze zijn daarmee
 * het antwoord op "wat kun je precies".
 *
 * **Een eigen tabel en geen JSON-kolom.** Met JSON kun je niet per regel
 * valideren en dus ook geen foutmelding bij de juiste regel zetten, en
 * sorteren wordt handwerk. Dat is dezelfde afweging als bij
 * `experience_stats`, en dezelfde als waarom vertalingen kolommen zijn
 * en geen JSON; zie docs/architecture/vertalingen.md.
 *
 * **Hoogstens acht per dienst**, en dat is een ontwerpgrens en geen
 * technische: acht korte labels vullen ongeveer drie regels onder de
 * tekst, en daarboven lopen de kaarten in het raster uit elkaar. De
 * grens wordt afgedwongen in de validatie; zie ServiceRequest.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_points', function (Blueprint $table) {
            $table->id();

            /*
             * Weg met de dienst. Een punt heeft in zijn eentje geen
             * betekenis, dus er valt hier niets te bewaren voor later.
             */
            $table->foreignId('service_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->unsignedInteger('position')->default(0);

            /*
             * Zestig tekens. Dit is een label en geen zin: past het er
             * niet in, dan hoort het in de tekst van de dienst zelf.
             *
             * Het Engels mag leeg blijven en valt níet terug op het
             * Nederlands. Een lijstje met drie Engelse en twee
             * Nederlandse labels is slordiger dan een lijstje van drie;
             * zie App\Models\Service.
             */
            $table->string('text_nl', 60);
            $table->string('text_en', 60)->nullable();

            $table->timestamps();

            $table->index(['service_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_points');
    }
};
