<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De module Contact: onderwerpen, velden, instellingen en de aanvragen.
 *
 * **De vierde tabel is de bijzondere.** `contact_submissions` is de eerste
 * plek in dit project waar inhoud van een bezoeker blijft staan. Dat is een
 * bewuste keuze met een bewaartermijn van een jaar, en de privacyverklaring
 * is in dezelfde wijziging aangepast; zie
 * docs/architecture/modules/contact.md en
 * docs/security/verzoeken-van-bezoekers.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        /*
         * De onderwerpen waaruit een bezoeker kan kiezen. Hetzelfde patroon
         * als elke andere module: tweetalig, aan/uit, versleepbaar.
         */
        Schema::create('contact_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('label_nl', 80);
            $table->string('label_en', 80)->nullable();
            $table->boolean('published')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();

            $table->index(['position', 'id']);
        });

        /*
         * Eén rij per veld dat het formulier kan hebben.
         *
         * Alleen de stand en de volgorde; wát een veld is staat in
         * App\Enums\ContactVeld. `key` is uniek, want twee rijen voor
         * hetzelfde veld is een formulier dat niet te verklaren is.
         */
        Schema::create('contact_fields', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->string('status', 16)->default('uit');
            $table->unsignedInteger('position')->default(0);

            /*
             * Of de bezoeker een eigen onderwerp mag typen.
             *
             * Deze kolom heeft alleen betekenis op de rij van het
             * onderwerp. Dat is licht onzuiver, en het alternatief -- een
             * instellingentabel voor één ja-nee -- is erger.
             */
            $table->boolean('allow_custom')->default(true);

            $table->timestamps();

            $table->index(['position', 'id']);
        });

        /*
         * De instellingen van de module. Eén rij, net als de koptekst.
         */
        Schema::create('contact_settings', function (Blueprint $table) {
            $table->id();
            $table->string('display', 16)->default('pagina');
            $table->string('confirmation_subject_nl', 160)->nullable();
            $table->string('confirmation_subject_en', 160)->nullable();
            $table->text('confirmation_body_nl')->nullable();
            $table->text('confirmation_body_en')->nullable();
            $table->timestamp('machine_translated_at')->nullable();
            $table->timestamps();
        });

        /*
         * De binnengekomen aanvragen.
         *
         * **Vaste kolommen en geen JSON-blok**, om twee redenen die allebei
         * zwaarwegen. Het scherm Juridisch moet op e-mailadres kunnen
         * zoeken met `LIKE ... ESCAPE '!'`, en dat gaat op een JSON-kolom
         * anders op SQLite -- waar de tests draaien -- dan op MySQL. En het
         * beheerscherm moet de velden netjes kunnen tonen zonder te raden
         * wat erin zit.
         *
         * De prijs is een migratie per nieuw veld. Dat is precies het
         * moment waarop iemand er toch naar moet kijken.
         */
        Schema::create('contact_submissions', function (Blueprint $table) {
            $table->id();

            /*
             * De taal waarin de bezoeker het formulier zag. Nodig voor de
             * bevestigingsmail, want de queue draait in een losse
             * opdrachtregel waar de taal van het verzoek niet meer bestaat.
             */
            $table->string('locale', 5)->default('nl');

            $table->string('name', 120);

            // Geïndexeerd omdat het scherm Juridisch hierop zoekt én
            // verwijdert bij een verzoek van een bezoeker.
            $table->string('email', 190)->index();

            $table->string('company', 120)->nullable();
            $table->string('phone', 32)->nullable();

            /*
             * Het onderwerp als verwijzing én als tekst.
             *
             * De tekst is wat de bezoeker koos of typte op dát moment, in
             * zijn taal. Hernoemt de eigenaar het onderwerp later, dan
             * blijft de aanvraag leesbaar zoals hij binnenkwam; verwijdert
             * hij het, dan wordt de verwijzing leeg en blijft de tekst
             * staan. Zelfde gedachte als in het activiteitenlogboek, waar
             * de naam als tekst wordt bewaard en niet als verwijzing.
             */
            $table->foreignId('subject_id')->nullable()
                ->constrained('contact_subjects')->nullOnDelete();
            $table->string('subject_text', 160);
            $table->boolean('subject_custom')->default(false);

            $table->text('message');

            /*
             * Welke velden er aan stonden toen dit binnenkwam.
             *
             * **Dit is het enige wat hier als JSON hoort**, en het is geen
             * antwoord maar een momentopname. Zonder dit kan het
             * beheerscherm niet zien of "Telefoonnummer" leeg is omdat de
             * bezoeker het oversloeg of omdat het veld toen niet bestond --
             * en dat is een ander verhaal. Er wordt nooit op gezocht.
             */
            $table->json('shown');

            // Door de eigenaar zelf bij te houden; hij antwoordt vanuit
            // zijn eigen mailprogramma en niet hier.
            $table->timestamp('read_at')->nullable();
            $table->timestamp('answered_at')->nullable();

            $table->timestamps();

            // Voor het opruimen na de bewaartermijn.
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_submissions');
        Schema::dropIfExists('contact_settings');
        Schema::dropIfExists('contact_fields');
        Schema::dropIfExists('contact_subjects');
    }
};
