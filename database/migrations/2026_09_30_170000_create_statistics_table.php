<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De statistieken op de landingspagina.
 *
 * Waar de tijdlijn vertelt wát de eigenaar heeft gedaan en de
 * certificaten waarvoor hij is getoetst, staat hier waar hij **goed in**
 * is -- in cijfers. Vaardigheden met een niveau, en kengetallen waar een
 * bezoeker op afgaat.
 *
 * **De klant kiest per statistiek zelf de vorm.** Een percentage kan een
 * balk of een ring zijn, een aantal wordt een teller. Vandaar één tabel
 * met een `display`-kolom, en niet drie tabellen die grotendeels
 * hetzelfde bevatten.
 *
 * **De grens op `value` staat niet hier maar in de FormRequest**, want
 * hij hangt van die weergave af: een balk of ring van 340% bestaat niet,
 * een teller van 340 wel. De kolom moet allebei aankunnen, dus die is
 * ruim.
 *
 * **Hele getallen.** De duizendtalscheiding komt er in de browser bij;
 * decimalen niet. Wie "99,9%" wil zet 99 neer met achtervoegsel ",9%" --
 * en als dat vaak voorkomt is een decimaal later één kolomwijziging.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistics', function (Blueprint $table) {
            $table->id();

            // De sleutel uit App\Enums\StatisticDisplay: balk, ring of
            // teller.
            $table->string('display', 16)->default('balk');

            $table->boolean('published')->default(true);

            /*
             * De volgorde is redactioneel, net als bij de diensten en de
             * certificaten. Let op: hij geldt *binnen* een groep en
             * binnen een weergave -- het blok bundelt de ringen, de
             * tellers en de balken elk apart. Zie de sectiecomponent.
             */
            $table->unsignedInteger('position')->default(0);

            $table->string('label_nl', 60);
            $table->string('label_en', 60)->nullable();

            /*
             * Ruim genoeg voor een teller van zeven cijfers. De echte
             * grens ligt per weergave in StatisticRequest.
             */
            $table->unsignedInteger('value')->default(0);

            // Het teken vóór en ná het getal: "€" en "+", "%", "/7".
            // Kort, want het staat tegen een groot getal aan.
            $table->string('prefix', 8)->nullable();
            $table->string('suffix', 8)->nullable();

            // Eén regeltje onder het label. Geen alinea: dit staat op een
            // tegel naast andere tegels.
            $table->string('note_nl', 120)->nullable();
            $table->string('note_en', 120)->nullable();

            /*
             * De groep doet twee dingen: `group_nl` is de **sleutel**
             * waarop gegroepeerd wordt én het Nederlandse kopje,
             * `group_en` is alleen het Engelse kopje.
             *
             * Dat is met opzet zo scheef. Zou je op de vertaalde waarde
             * groeperen, dan valt een groep in het Engels uit elkaar
             * zodra één item zijn Engelse groepsnaam mist. Nu is de
             * indeling in beide talen gegarandeerd dezelfde en is de
             * vertaling puur opmaak.
             *
             * Leeg betekent: los bovenaan, boven de eerste kop.
             */
            $table->string('group_nl', 60)->nullable();
            $table->string('group_en', 60)->nullable();

            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();

            // Op volgorde ophalen is wat de website en het beheerscherm
            // allebei doen, dus die index verdient zich terug.
            $table->index(['position', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statistics');
    }
};
