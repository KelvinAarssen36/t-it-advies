<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De projecten die de eigenaar laat zien.
 *
 * **Eén tabel en geen tweede ervaringenmodule.** Ervaring is zijn
 * loopbaan: waar hij in dienst was, op een tijdlijn, zonder instelbare
 * volgorde. Dit is zijn etalage: wat hij heeft gedáán, in de volgorde
 * die hij zelf kiest, met één stuk dat hij vooraan zet. Twee modules
 * met hetzelfde gevoel maar een andere vraag erachter.
 *
 * **Daarom het veld `type`.** Niet elk item is een klassiek project --
 * het kan een interim-opdracht zijn, een migratie of een stage. De
 * module heet Projecten, maar een item draagt zijn eigen woord. Zie
 * App\Enums\ProjectType.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();

            /*
             * Lager staat hoger op de pagina. De eigenaar sleept zelf, dus
             * anders dan bij Ervaring is er hier geen vaste sortering op
             * datum: een project van vijf jaar geleden mag vooraan staan
             * als dat het beste laat zien wat hij kan.
             */
            $table->unsignedSmallInteger('position')->index();

            $table->boolean('published')->default(true);

            /*
             * Hoogstens één project tegelijk. Dat "hoogstens één" staat
             * niet in de database maar in ProjectController::uitlichten(),
             * die in één transactie de rest uitzet -- een unieke index op
             * een boolean kan "nul of één keer true" niet uitdrukken
             * zonder een partiële index, en die kent SQLite anders dan
             * MySQL. De tests bewaken het gedrag.
             */
            $table->boolean('featured')->default(false);

            /*
             * Het adres van de detailpagina, bijvoorbeeld
             * `zorgkoepel-migratie`. Bij het aanmaken afgeleid van de
             * Nederlandse titel en daarna nooit meer bijgewerkt: een link
             * die iemand heeft gedeeld hoort te blijven werken, ook als de
             * titel later verandert.
             *
             * 160 tekens past binnen de indexgrens van MySQL met utf8mb4.
             */
            $table->string('slug', 160)->unique();

            /*
             * Een sleutel uit ProjectType. Niet nullable: er is altijd een
             * soort, en 'project' is de standaard.
             */
            $table->string('type', 32)->default('project');

            /*
             * Alleen gevuld bij het type "anders". Tweetalig, want het is
             * een woord dat de bezoeker leest.
             */
            $table->string('type_label_nl', 60)->nullable();
            $table->string('type_label_en', 60)->nullable();

            $table->string('title_nl', 120);
            $table->string('title_en', 120)->nullable();

            /*
             * Niet tweetalig: een bedrijfsnaam is een eigennaam. Zelfde
             * afweging als `experiences.organisation`.
             */
            $table->string('organisation', 120);

            $table->string('role_nl', 120);
            $table->string('role_en', 120)->nullable();

            /*
             * Alleen de maand en het jaar doen ertoe; de dag staat altijd
             * op 1 en betekent niets. Een echte datumkolom is toch
             * handiger dan twee getallen: sorteren, vergelijken en
             * opmaken werken dan gewoon. Zelfde keuze als bij Ervaring.
             *
             * `ended_on` leeg betekent "loopt nog". Dat is geen
             * ontbrekende waarde maar een betekenisvolle.
             */
            $table->date('started_on');
            $table->date('ended_on')->nullable();

            /*
             * De regel op de kaart. Verplicht in het Nederlands, want een
             * kaart zonder samenvatting is een lege tegel.
             */
            $table->string('summary_nl', 300);
            $table->string('summary_en', 300)->nullable();

            $table->text('body_nl')->nullable();
            $table->text('body_en')->nullable();

            /*
             * Wat het opleverde. Het enige extra prozaveld in deze module:
             * bij een adviesopdracht is de uitkomst het interessantste
             * deel, en die past niet in de omschrijving van het werk.
             */
            $table->text('result_nl')->nullable();
            $table->text('result_en')->nullable();

            $table->string('image_path')->nullable();

            /*
             * Wanneer de Engelse tekst door de vertaaldienst is gemaakt in
             * plaats van door de klant zelf. Leeg betekent "met de hand".
             * Zie docs/architecture/automatisch-vertalen.md.
             */
            $table->timestamp('machine_translated_at')->nullable();

            $table->timestamps();

            // Voor het overzicht op datum; de site sorteert op position.
            $table->index(['started_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
