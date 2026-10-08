<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * De keuze om na een passkey alsnog een authenticator-code te vragen.
 *
 * **Standaard uit.** Dit is een extra slot dat de eigenaar zelf omzet, en
 * een slot dat je niet hebt gekozen is geen beveiliging maar een
 * verrassing. Wie hem aanzet weet waarom.
 *
 * Op `users` en niet in de site-instellingen: dit gaat over hoe één
 * persoon inlogt, niet over hoe de website eruitziet.
 *
 * Zie docs/security/extra-stap-na-een-passkey.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('passkey_requires_two_factor')
                ->default(false)
                ->after('two_factor_confirmed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('passkey_requires_two_factor');
        });
    }
};
