<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De tabellen voor de bezoekcijfers.
 *
 * **Vier tabellen in één migratie, anders dan de rest van dit project.** Ze
 * zijn los van elkaar zinloos: de codes bestaan alleen om de tellers in de
 * dagtotalen te kunnen vullen, en het zout bestaat alleen om die codes te
 * maken. Ze worden samen aangemaakt en samen weggegooid, dus ze horen in één
 * bestand.
 *
 * **Er staat met opzet geen rij per bezoek in.** Dat is de kern van het
 * ontwerp, en het heeft twee redenen:
 *
 * 1. **Privacy.** Een logboek met een rij per bezoek is een tijdlijn van
 *    wie wanneer op de site was. Tellers zijn dat niet; die zeggen alleen
 *    "op deze dag zoveel".
 * 2. **Gedeelde hosting.** Een tabel die met elk bezoek een rij erbij
 *    krijgt, groeit onbeperkt en moet opgeruimd worden. Deze tabellen
 *    groeien met 365 rijen per jaar.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * De dagtotalen. Eén rij per dag, drie tellers.
         *
         * `visitors` is géén eigen berekening maar een teller die omhoog
         * gaat zodra er een code bij komt die vandaag nog niet bestond. Zo
         * is het aantal bezoekers van gisteren een getal dat blijft staan,
         * ook nadat de codes zijn weggegooid.
         */
        Schema::create('site_day_totals', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->unsignedInteger('contacts')->default(0);
            $table->timestamps();
        });

        /*
         * De uitsplitsingen: verwijzer, apparaat en taal.
         *
         * Eén tabel voor alle drie en geen kolom per soort. Daarmee is een
         * vierde uitsplitsing later een nieuwe waarde in `kind` en geen
         * migratie -- en dat is precies het soort wijziging waarvan je
         * vooraf weet dat hij komt.
         */
        Schema::create('site_day_dimensions', function (Blueprint $table) {
            $table->id();
            $table->date('day');

            // 'verwijzer', 'apparaat' of 'taal'; zie BezoekDimensie.
            $table->string('kind', 20);

            /*
             * De waarde als sleutel en niet als opschrift: 'linkedin',
             * 'mobiel', 'nl'. Het portaal is tweetalig, dus het opschrift
             * hoort in de frontend te worden gekozen. Een onbekende
             * verwijzer staat hier als zijn host.
             */
            $table->string('name', 100);

            $table->unsignedInteger('views')->default(0);
            $table->timestamps();

            $table->unique(['day', 'kind', 'name']);
        });

        /*
         * "Is deze bezoeker vandaag al geteld."
         *
         * Een onomkeerbare code uit het dagzout, het IP-adres en het
         * browserkenmerk. Het IP-adres zelf staat hier niet, en kan er niet
         * uit teruggerekend worden zodra het zout van die dag weg is.
         *
         * **Met opzet geen `timestamps()`.** Een tijdstip per bezoeker is
         * precies het gegeven dat een code weer naar een persoon toe
         * brengt: wie om 09:14 op de site was, is een veel kleinere groep
         * dan wie er die dag was. De datum is genoeg.
         */
        Schema::create('site_visitor_codes', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->char('code', 64);

            $table->unique(['day', 'code']);
        });

        /*
         * Het zout van vandaag, en niets anders.
         *
         * Bij het eerste bezoek na middernacht komt hier een nieuwe rij en
         * verdwijnen de oude -- samen met alle codes van voor vandaag. Dat
         * opruimen hangt dus aan het zout en niet aan een nachtelijke
         * cron: een belofte die afhangt van een cronregel die iemand
         * vergeet, is geen belofte.
         */
        Schema::create('site_visitor_salts', function (Blueprint $table) {
            $table->date('day')->primary();
            $table->string('salt', 64);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_visitor_salts');
        Schema::dropIfExists('site_visitor_codes');
        Schema::dropIfExists('site_day_dimensions');
        Schema::dropIfExists('site_day_totals');
    }
};
