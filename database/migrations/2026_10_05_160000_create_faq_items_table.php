<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De veelgestelde vragen.
 *
 * Eén tabel, één lijst, geen groepen. Dat laatste is een keuze van de
 * eigenaar: bij een handvol vragen voegt een kopje niets toe, en het
 * beheerscherm blijft er leeg van. Komt er ooit een tweede onderwerp bij
 * -- "over tarieven", "over samenwerken" -- dan is dat een kolom erbij en
 * een vak in het indelingsvenster, zoals bij de statistieken.
 *
 * **Vraag en antwoord liggen qua lengte ver uit elkaar**, en dat staat
 * hier in de kolommen. Een vraag is een regel: `string` van 160, want hij
 * moet dichtgeklapt op één regel passen op een telefoon. Een antwoord is
 * `text`: daar mogen witregels in, die worden op de website alinea's.
 *
 * **Geen aparte tabel voor het antwoord**, anders dan bij de
 * expertisepunten van een dienst. Een vraag heeft er precies één, altijd,
 * en een tabel met een één-op-éénrelatie is alleen een extra join.
 *
 * Het Engels mag leeg blijven en valt per veld terug op het Nederlands --
 * een lijst met een gat erin is geen lijst. Zie App\Models\FaqItem.
 *
 * Zie docs/architecture/modules/faq.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('faq_items', function (Blueprint $table) {
            $table->id();

            /*
             * Honderdzestig tekens voor de vraag.
             *
             * Dichtgeklapt staat een vraag op één regel met een chevron
             * ernaast. Op een telefoon is dat ruim twee regels tekst, en
             * dat is de grens: past de vraag er niet in, dan is het geen
             * vraag meer maar het begin van het antwoord.
             */
            $table->string('question_nl', 160);
            $table->string('question_en', 160)->nullable();

            /*
             * Het antwoord als `text` en niet als `string`.
             *
             * Hier mogen witregels in staan; `brand-blad-tekst` maakt
             * daar op de website alinea's van, dezelfde klasse als in het
             * venster van een dienst. Geen opmaakbalkje dus, en daarmee
             * ook niets dat scheef kan staan.
             */
            $table->text('answer_nl');
            $table->text('answer_en')->nullable();

            /*
             * Of de vraag op de website staat. Standaard aan: iets dat je
             * invoert wil je ook laten zien.
             */
            $table->boolean('published')->default(true);

            $table->unsignedInteger('position')->default(0);

            /*
             * Wanneer het Engels van de vertaaldienst kwam, of `null` als
             * de eigenaar het zelf heeft geschreven of nagelezen. Zie
             * docs/architecture/automatisch-vertalen.md.
             */
            $table->timestamp('machine_translated_at')->nullable();

            $table->timestamps();

            /*
             * De index die de website gebruikt: alleen wat online staat,
             * op volgorde. Dat is de enige query die per bezoek draait.
             */
            $table->index(['published', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('faq_items');
    }
};
