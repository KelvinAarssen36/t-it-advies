<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De stappen van de werkwijze: van kennismaken tot overdragen.
 *
 * **Deze stappen stonden in het Vue-bestand.** Vier stuks, hardgecodeerd,
 * met de kop erboven net zo. Mooi gemaakt maar niet van de klant -- en dit
 * is zijn site. Nu beheert hij ze zelf.
 *
 * Opgezet als `services`: een eigen volgorde, een schuifje per rij, en
 * twee talen naast elkaar. Wat er anders is staat in
 * docs/architecture/modules/werkwijze.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_steps', function (Blueprint $table) {
            $table->id();

            /*
             * De volgorde is hier geen voorkeur maar betekenis: het
             * nummer op de kaart volgt eruit. Sleept de eigenaar stap
             * drie naar voren, dan is dat voortaan 01.
             */
            $table->smallInteger('position')->index();

            $table->boolean('published')->default(true);

            $table->string('title_nl', 80);
            $table->string('title_en', 80)->nullable();

            /*
             * De zin onder de titel, op de kaart. Verplicht: een stap met
             * alleen een titel zegt niets over wat er gebeurt.
             */
            $table->string('summary_nl', 300);
            $table->string('summary_en', 300)->nullable();

            /*
             * Hoe lang de stap duurt, als tekst en niet als getal.
             * "1-2 weken", "een middag", "doorlopend" -- dat laat zich
             * niet in dagen uitdrukken, en een schatting die te precies
             * oogt is een belofte die je niet wilde doen.
             */
            $table->string('duration_nl', 40)->nullable();
            $table->string('duration_en', 40)->nullable();

            /*
             * Wat de klant aan het eind van deze stap in handen heeft.
             * Kort gehouden: dit is één regel op een smalle kaart, geen
             * alinea.
             */
            $table->string('result_nl', 160)->nullable();
            $table->string('result_en', 160)->nullable();

            /*
             * Het hele verhaal, voor de pagina /werkwijze. Leeg laten mag:
             * dan bestaat die pagina gewoon niet voor deze stap, en zonder
             * ook maar één verhaal verschijnt de knop ernaartoe niet.
             */
            $table->text('body_nl')->nullable();
            $table->text('body_en')->nullable();

            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_steps');
    }
};
