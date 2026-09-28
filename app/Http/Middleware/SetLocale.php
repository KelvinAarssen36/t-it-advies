<?php

namespace App\Http\Middleware;

use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bepaalt bij elk verzoek in welke taal de applicatie antwoordt.
 *
 * Dit is de **enige** plek waar dat gebeurt. Nergens anders staat een
 * `App::setLocale()`; een knop schrijft alleen naar de bronnen hieronder en
 * deze middleware leest ze. Zo kan er geen scherm zijn dat zijn eigen taal
 * kiest en daarmee van de rest afwijkt.
 *
 * De volgorde is betekenisvol: het profiel wint van de sessie. Anders zou
 * iemand die op een gedeelde computer even naar Engels wisselt daarmee de
 * voorkeur van de eigenaar overschrijven.
 *
 * Zie docs/architecture/vertalingen.md.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $taal = $this->resolve($request);

        App::setLocale($taal);

        /*
         * Carbon heeft zijn eigen taal, los van die van de applicatie.
         * Zonder deze regel blijft "2 hours ago" staan in een Nederlands
         * portaal, en heten de maanden nog steeds "Sep" in plaats van
         * "sep". Zie App\Support\Datum.
         */
        CarbonImmutable::setLocale($taal);

        return $next($request);
    }

    private function resolve(Request $request): string
    {
        $user = $request->user();

        if ($user instanceof User && $this->isAllowed($user->locale)) {
            return (string) $user->locale;
        }

        $fromSession = $request->session()->get('locale');

        if ($this->isAllowed($fromSession)) {
            return (string) $fromSession;
        }

        return (string) config('app.locale');
    }

    /**
     * Alles wat niet op de witte lijst staat, telt niet mee.
     */
    private function isAllowed(mixed $locale): bool
    {
        return is_string($locale)
            && array_key_exists($locale, (array) config('app.available_locales'));
    }
}
