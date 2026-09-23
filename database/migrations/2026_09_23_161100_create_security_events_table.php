<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('security_events', function (Blueprint $table) {
            $table->id();

            // Kan null zijn: een mislukte login hoort vaak bij geen enkele
            // bestaande gebruiker. We bewaren dan alleen de opgegeven e-mail.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('email')->nullable();

            $table->string('event')->index();
            $table->string('outcome')->index();

            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();

            // Altijd geschoond door SecurityLogger. Nooit wachtwoorden,
            // TOTP-codes, secrets of recovery codes.
            $table->json('context')->nullable();

            $table->timestamp('created_at')->index();

            $table->index(['user_id', 'created_at']);
            $table->index(['event', 'outcome', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('security_events');
    }
};
