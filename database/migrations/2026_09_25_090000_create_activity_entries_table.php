<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_entries', function (Blueprint $table) {
            $table->id();

            /*
             * Wie het deed. Kan null zijn bij een geplande taak of een
             * commando, en dan staat er 'Systeem' in actor_name.
             *
             * De naam staat er als momentopname naast, niet alleen als
             * relatie. Wordt een account later verwijderd, dan blijft de
             * regel leesbaar; zonder die kopie staat er dan "iemand heeft
             * dit gewijzigd" en heb je er niets meer aan.
             */
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('actor_name');

            $table->string('action')->index();

            /*
             * Waarop. Het type is de modelklasse en het label is opnieuw een
             * momentopname: na een verwijdering bestaat de rij niet meer,
             * maar je wilt nog wel kunnen lezen wát er weg is.
             */
            $table->string('subject_type')->index();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('subject_label')->nullable();

            /*
             * Wat er veranderde, per veld: {"titel": {"van": "…", "naar": "…"}}.
             * Altijd geschoond door ActivityLogger -- nooit wachtwoorden,
             * tokens of secrets. Zie docs/security/logging.md.
             */
            $table->json('changes')->nullable();

            $table->string('ip_address', 45)->nullable();

            $table->timestamp('created_at')->index();

            $table->index(['subject_type', 'subject_id']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_entries');
    }
};
