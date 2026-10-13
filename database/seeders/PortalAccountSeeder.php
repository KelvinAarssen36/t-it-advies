<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * Het account van de eigenaar.
 *
 * Dit is geen testdata. Deze applicatie heeft één gebruiker en dat account
 * hoort in elke omgeving te bestaan, ook in productie. Daarom draait deze
 * seeder mee bij elke deploy.
 *
 * Twee eigenschappen die het verschil maken:
 *
 * - **Idempotent.** Bestaat het account al, dan blijft het zoals het is.
 *   Zou de seeder het wachtwoord bijwerken, dan draait elke deploy een
 *   wachtwoord terug dat de eigenaar inmiddels zelf heeft gewijzigd -- en
 *   dat merk je pas wanneer hij niet meer binnenkomt.
 * - **Geen wachtwoord in code.** Dat komt uit `PORTAL_ACCOUNT_PASSWORD` in
 *   `.env`. Ontbreekt die waarde terwijl het account nog niet bestaat, dan
 *   stopt de seeder met een duidelijke melding in plaats van stilletjes een
 *   account zonder bruikbaar wachtwoord achter te laten.
 *
 * Zie docs/security/rollen-en-rechten.md.
 */
class PortalAccountSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array{name: string, email: string, password: string|null, role: string} $account */
        $account = config('security.portal_account');

        // Kleine letters, want SQLite vergelijkt hoofdlettergevoelig. Zonder
        // dit zou een account dat met een hoofdletter is geseed lokaal niet
        // gevonden worden bij het inloggen.
        $email = mb_strtolower(trim($account['email']));

        $bestaand = User::query()->where('email', $email)->first();

        if ($bestaand !== null) {
            // Wel de rol en de verificatie borgen: die kunnen zijn weggevallen
            // door een nieuwe database of een gewijzigde rollenlijst.
            $this->ensureRoleAndVerification($bestaand, $account['role']);

            return;
        }

        $wachtwoord = $account['password'];

        if (! is_string($wachtwoord) || trim($wachtwoord) === '') {
            throw new RuntimeException(
                'PORTAL_ACCOUNT_PASSWORD ontbreekt in .env. '
                .'Zonder die waarde kan het account van de eigenaar niet worden aangemaakt.'
            );
        }

        $gebruiker = User::create([
            'name' => $account['name'],
            'email' => $email,
            'password' => $wachtwoord,
        ]);

        $this->ensureRoleAndVerification($gebruiker, $account['role']);
    }

    /**
     * Zonder geverifieerd e-mailadres komt de eigenaar het beheergedeelte
     * niet in, en bij het opzetten staat de mailprovider vaak nog niet.
     */
    private function ensureRoleAndVerification(User $gebruiker, string $rol): void
    {
        if ($gebruiker->email_verified_at === null) {
            $gebruiker->forceFill(['email_verified_at' => now()])->save();
        }

        if (! $gebruiker->hasRole($rol)) {
            $gebruiker->assignRole($rol);
        }
    }
}
