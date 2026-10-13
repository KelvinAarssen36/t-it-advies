<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Houdt het portaal dicht tot tweestapsverificatie is bevestigd.
 *
 * Fortify levert de machinerie; deze middleware legt de plicht op. Er is
 * dus geen knop "zet 2FA aan" die je kunt overslaan: bij je eerste bezoek
 * word je naar de instelpagina gestuurd en kom je daar pas vanaf met een
 * werkende code.
 *
 * Bewust als route-middleware op de groepen van het portaal, en niet
 * globaal: globaal zou hij ook over het inlogscherm en de foutpagina's
 * lopen.
 *
 * De volgorde van de poortjes is het ontwerp. Zie
 * docs/security/authenticatie-en-2fa.md.
 */
class EnsureTwoFactorIsConfigured
{
    /**
     * Routes die je nodig hebt om 2FA in te stellen, of om weg te komen.
     *
     * @var array<int, string>
     */
    private const ALLOWED = [
        'security.two-factor.setup',
        'security.two-factor.setup.finish',
        'logout',
    ];

    /**
     * Hele families van Fortify-routes die hetzelfde doel dienen.
     *
     * @var array<int, string>
     */
    private const ALLOWED_PREFIXES = [
        'two-factor.',        // aanzetten, QR, sleutel, bevestigen, challenge
        'password.confirm',   // Fortify vraagt je wachtwoord voor het aanzetten
        'verification.',      // e-mailverificatie
    ];

    public function handle(Request $request, Closure $next): Response
    {
        // 1. Staat de eis uit, dan doet deze middleware niets.
        if (! config('security.two_factor.required')) {
            return $next($request);
        }

        $user = $request->user();

        // 2. Niemand ingelogd, of e-mail nog niet geverifieerd? Laat de
        //    middleware die daarover gaat zijn werk doen. Twee
        //    verplichtingen die allebei omleiden, leiden tot een lus.
        if (! $user instanceof User || ! $user->hasVerifiedEmail()) {
            return $next($request);
        }

        // 3. Bevestigd? Klaar. Let op het verschil met een bestaand geheim:
        //    wie halverwege afhaakt heeft wel een geheim, maar is niet
        //    beveiligd.
        if ($user->two_factor_confirmed_at !== null) {
            return $next($request);
        }

        // 4. Vraag je juist een route op die je nodig hebt om het in te
        //    stellen? Zonder deze uitzondering sluit de middleware je buiten
        //    van de pagina waar hij je naartoe stuurt.
        if ($this->isSetupRoute($request)) {
            return $next($request);
        }

        // 5. `guest()` en niet een gewone redirect: dat onthoudt waar je
        //    heen wilde, zodat je ná het instellen op je oorspronkelijke
        //    bestemming landt in plaats van op het dashboard.
        return redirect()->guest(route('security.two-factor.setup'));
    }

    private function isSetupRoute(Request $request): bool
    {
        $name = $request->route()?->getName();

        if ($name === null) {
            return false;
        }

        return in_array($name, self::ALLOWED, true)
            || Str::startsWith($name, self::ALLOWED_PREFIXES);
    }
}
