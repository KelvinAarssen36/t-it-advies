<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De kerngegevens: de harde feiten over zakendoen met de eigenaar.
 *
 * Beschikbaarheid, werkgebied, reactietijd -- de vragen die een bezoeker
 * stelt vlak voordat hij belt. De website vertelde tot nu toe wie hij is
 * en wat hij doet, maar niet of en hoe je met hem in zee kunt.
 *
 * **Het verschil met `statistics` is een waarde in tekst.** Een statistiek
 * is een getal met een balk of ring eromheen; het beeld is de boodschap.
 * Hier staat "Vanaf januari, 2 tot 3 dagen per week", en dat laat zich in
 * geen enkel percentage persen.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('core_facts', function (Blueprint $table) {
            $table->id();

            /*
             * Het soort, uit App\Enums\CoreFactIcon. Dat bepaalt het
             * pictogram én stelt in het beheerscherm het label voor.
             */
            $table->string('icon', 32)->default('beschikbaarheid');

            $table->boolean('published')->default(true);
            $table->unsignedInteger('position')->default(0);

            /*
             * Het label is kort met opzet: het staat klein en gedempt
             * boven de waarde, en een label dat over twee regels loopt
             * breekt de strook.
             */
            $table->string('label_nl', 60);
            $table->string('label_en', 60)->nullable();

            /*
             * De waarde is wat de bezoeker leest. Tachtig tekens is ruim
             * voor "Op locatie, op afstand of een combinatie" en te krap
             * om er een alinea van te maken -- en dat laatste is de
             * bedoeling.
             */
            $table->string('value_nl', 80);
            $table->string('value_en', 80)->nullable();

            // Eén regel toelichting eronder, en mag leeg blijven.
            $table->string('note_nl', 120)->nullable();
            $table->string('note_en', 120)->nullable();

            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();

            // Zelfde index als bij de andere lijsten: `position` met `id`
            // erachter, want twee rijen op dezelfde plek zouden anders per
            // query van volgorde wisselen.
            $table->index(['position', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('core_facts');
    }
};
