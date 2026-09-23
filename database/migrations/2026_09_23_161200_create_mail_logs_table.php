<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_logs', function (Blueprint $table) {
            $table->id();

            // Message-ID uit de mailheader. Hiermee koppelen we
            // provider-webhooks aan de mail die wij hebben verstuurd.
            $table->string('message_id')->nullable()->unique();

            $table->string('mailable')->nullable();
            $table->string('mailer')->nullable();
            $table->string('subject')->nullable();

            $table->json('to');
            $table->json('cc')->nullable();
            $table->json('bcc')->nullable();

            $table->string('status')->default('sent')->index();
            $table->text('error')->nullable();

            // Volledige tijdlijn van provider-events (delivered, bounced, ...).
            $table->json('events')->nullable();

            $table->timestamp('sent_at')->nullable()->index();
            $table->timestamp('last_event_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
