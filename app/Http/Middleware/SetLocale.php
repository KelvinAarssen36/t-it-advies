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
 * De volgorde is betekenisvol, van sterk naar zwak:
 *
 * 1. **Het profiel** van de ingelogde gebruiker. Dat is een uitgesproken
 *    keuze en wint van alles.
 * 2. **De sessie**, voor een bezoeker die op de knop drukte. Ook een
 *    keuze, maar niet vastgelegd.
 * 3. **Wat de browser vraagt.** Staat die op Nederlands, dan krijgt hij
 *    Nederlands; alle andere talen krijgen Engels. Zonder deze stap zou
 *    een Duitse of Engelse bezoeker een Nederlandse site voorgeschoteld
 *    krijgen en zelf moeten ontdekken dat er een knop is.
 * 4. **De instelling uit config**, als laatste vangnet.
 *
 * Het profiel wint van de sessie, want anders zou iemand die op een
 * gedeelde computer even naar Engels wisselt daarmee de voorkeur van de
 * eigenaar overschrijven. En de sessie wint van de browser, want wie zelf
 * op de knop drukt heeft daarmee het laatste woord -- ook al staat zijn
 * browser op iets anders.
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

        $response = $next($request);

        /*
         * Het antwoord hangt af van de taal die de browser vraagt, en dat
         * hoort elke cache ertussen te weten. Zonder deze kop kan een
         * proxy de Nederlandse pagina teruggeven aan een Engelse bezoeker
         * -- en dat is precies het soort fout dat je zelf nooit ziet,
         * want jouw browser vraagt altijd hetzelfde.
         */
        $response->headers->set('Vary', 'Accept-Language', false);

        return $response;
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

        return $this->vanDeBrowser($request);
    }

    /**
     * Wat de browser van de bezoeker vraagt.
     *
     * **Engels staat vooraan in de lijst, en dat is geen willekeur.**
     * `getPreferredLanguage` geeft het eerste element terug zodra er
     * niets matcht -- dus een Duitse, Franse of Poolse bezoeker krijgt
     * Engels, en alleen wie om Nederlands vraagt krijgt Nederlands. Zet
     * je `nl` vooraan, dan krijgt de halve wereld ineens Nederlands.
     *
     * `nl-BE` en `nl-NL` tellen allebei als Nederlands; Symfony kijkt
     * naar het taaldeel en niet naar het land.
     *
     * We kijken naar de **taal van de browser** en niet naar het land van
     * het IP-adres. Dat laatste vraagt een dienst van buiten, is bij een
     * VPN meteen fout, en zegt sowieso minder: iemand die op vakantie in
     * Spanje zit wil nog steeds Nederlands lezen.
     */
    private function vanDeBrowser(Request $request): string
    {
        $voorkeur = $request->getPreferredLanguage(['en', 'nl']);

        return $this->isAllowed($voorkeur)
            ? (string) $voorkeur
            : (string) config('app.locale');
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
