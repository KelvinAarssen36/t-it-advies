<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Elke aanvraag om het inlogadres te wijzigen.
 *
 * **Het adres op `users` verandert pas als het nieuwe zich heeft
 * bewezen.** Daarvóór staat de aanvraag hier te wachten. Dat is de hele
 * reden dat deze tabel bestaat: een typefout in het e-mailveld kan de
 * eigenaar dan nooit buitensluiten, want hij logt gewoon nog met zijn
 * oude adres in.
 *
 * **Een eigen tabel en geen kolommen op `users`.** Hier hoort geschiedenis
 * bij -- wélke wijziging, wanneer, en of hij is teruggedraaid -- en dat is
 * precies wat je nodig hebt op de dag dat er iets misgaat. Bovendien
 * blijven de tokens zo buiten de gebruikersrij.
 *
 * **Er is geen statuskolom.** De stand volgt uit de tijdstempels, en twee
 * dingen die hetzelfde zeggen kunnen uit elkaar gaan lopen. Zie
 * `EmailChange::isOpen()` en de scopes daar.
 *
 * Zie docs/security/inlogadres-wijzigen.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('email_changes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            /*
             * Allebei de adressen worden bewaard, ook het oude. Zonder dat
             * oude adres kan de herstellink niet weten waar hij naartoe
             * moet terugdraaien -- en dan is er geen weg terug.
             */
            $table->string('from_email');
            $table->string('to_email');

            /*
             * De twee tokens, allebei **gehasht**.
             *
             * Ze staan hier zoals een wachtwoord: wie de database leest
             * kan er niets mee. Een token in platte tekst is een sleutel
             * die in de la ligt. Ze komen ook nooit in een logregel; zie
             * AGENTS.md, regel 3.
             */
            $table->string('confirm_token', 64)->unique();
            $table->string('revert_token', 64)->nullable()->unique();

            $table->timestamp('requested_at');

            /*
             * Tot wanneer de bevestiging geldig is. Kort: dit is een link
             * naar een postbus die je net hebt opgegeven, en als die niet
             * binnen een uur wordt geopend klopt er iets niet.
             */
            $table->timestamp('expires_at');

            $table->timestamp('confirmed_at')->nullable();

            /*
             * En tot wanneer de weg terug openstaat. Ruim, want dit is het
             * vangnet: merk je pas na een week dat je niet meer binnenkomt,
             * dan moet die link het nog doen.
             */
            $table->timestamp('revert_expires_at')->nullable();
            $table->timestamp('reverted_at')->nullable();

            $table->timestamps();

            // Eén openstaande aanvraag per gebruiker opzoeken.
            $table->index(['user_id', 'confirmed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('email_changes');
    }
};
