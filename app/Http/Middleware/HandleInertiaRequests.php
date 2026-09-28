<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Str;
use Inertia\Middleware;
use Spatie\Honeypot\Honeypot;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                // De frontend gebruikt dit alleen om menu-items te tonen of te
                // verbergen. De echte controle gebeurt altijd op de server.
                'permissions' => $user?->getAllPermissions()->pluck('name')->all() ?? [],
                // Expliciet een boolean, en niet de datum uit het model:
                // `two_factor_confirmed_at` is een implementatiedetail van
                // Fortify, en de frontend hoeft alleen te weten of het
                // aanstaat. Zie TwoFactorNudge.vue.
                'twoFactor' => $user?->two_factor_confirmed_at !== null,
                // Of het wachtwoord nog vers bevestigd is. Zie
                // passwordConfirmed().
                'passwordConfirmed' => $this->passwordConfirmed($request),
            ],
            // De taal en de keuzelijst, zodat elke wisselknop weet waar hij
            // staat zonder het zelf op te zoeken.
            'locale' => app()->getLocale(),
            'locales' => (array) config('app.available_locales'),
            // De woordenlijst van de actieve taal. Zie translations().
            'translations' => fn () => $this->translations(),
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            // De eenmalige begroeting na het inloggen. Zie welcome().
            'welcome' => fn () => $this->welcome($request),
            // De honeypotvelden wisselen per request, dus die moeten mee met
            // elke paginarespons. Zie resources/js/components/HoneypotFields.vue.
            'honeypot' => fn () => app(Honeypot::class)->toArray(),
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }

    /**
     * Of het wachtwoord van deze sessie nog vers bevestigd is.
     *
     * Laravel legt het tijdstip bij een bevestiging in de sessie en de
     * middleware `password.confirm` rekent daarmee af tegen
     * `config('auth.password_timeout')`. Diezelfde som staat hier, zodat de
     * frontend het slotje naast "Beveiliging" open of dicht kan tonen.
     *
     * Dit is **weergave, geen beveiliging**. Wie deze waarde in de browser
     * omzet komt nergens: de middleware doet de controle opnieuw, op de
     * sessie waar de browser niet bij kan.
     */
    private function passwordConfirmed(Request $request): bool
    {
        if ($request->user() === null) {
            return false;
        }

        $bevestigdOp = $request->session()->get('auth.password_confirmed_at');

        if (! is_int($bevestigdOp)) {
            return false;
        }

        return (time() - $bevestigdOp) < (int) config('auth.password_timeout', 10800);
    }

    /**
     * De woordenlijst van de actieve taal, voor de frontend.
     *
     * Dit zijn dezelfde `lang/*.json`-bestanden die de serverkant gebruikt,
     * opgehaald via de loader van Laravel zelf, zodat er maar één bron is.
     *
     * Nederlands is de sleuteltaal: `lang/nl.json` is leeg en blijft leeg,
     * want de sleutel ís de Nederlandse tekst. In de standaardtaal gaat er
     * dus niets over de lijn. Alleen wie Engels kiest betaalt de paar
     * kilobyte.
     *
     * Het is een closure, dus bij een gedeeltelijke herlading die deze prop
     * niet opvraagt wordt er ook niets ingelezen.
     *
     * @return object De woordenlijst; een object, zodat een lege lijst in
     *                JSON `{}` wordt en niet `[]`.
     */
    private function translations(): object
    {
        /** @var array<string, string> $regels */
        $regels = app('translator')->getLoader()->load(app()->getLocale(), '*', '*');

        return (object) $regels;
    }

    /**
     * De eenmalige begroeting na het inloggen.
     *
     * Dit hoort hier en niet in de controller van het dashboard, want je
     * landt lang niet altijd op het dashboard: had je een pagina open staan
     * voordat je sessie afliep, dan kom je daar terug. Elke pagina van het
     * portaal moet dus kunnen groeten.
     *
     * De vlag wordt met `pull` opgehaald en meteen gewist, zodat de
     * begroeting één keer verschijnt en niet opnieuw bij elke verversing.
     * Twee voorwaarden voordat dat gebeurt:
     *
     * 1. **Niet op het laadscherm zelf.** Dat scherm zet de vlag in dezelfde
     *    request; zou hij hem hier ook meteen ophalen, dan is hij op voordat
     *    er iets te zien is geweest.
     * 2. **Alleen op een pagina van het portaal**, herkenbaar aan de
     *    middleware 'two-factor.required'. Kom je na het inloggen eerst
     *    langs het instellen van 2FA of een wachtwoordbevestiging, dan
     *    blijft de vlag staan tot je echt binnen bent -- anders wordt hij
     *    opgebruikt door een scherm dat niets toont.
     *
     * @return array{firstName: string}|null
     */
    private function welcome(Request $request): ?array
    {
        $user = $request->user();
        $route = $request->route();

        if ($user === null || ! $route instanceof Route) {
            return null;
        }

        if ($route->getName() === 'portal.enter') {
            return null;
        }

        if (! in_array('two-factor.required', $route->gatherMiddleware(), true)) {
            return null;
        }

        if (! $request->session()->pull('portal.welcome', false)) {
            return null;
        }

        return [
            // Alleen de voornaam. "Welkom terug, Erik Aarssen" klinkt als een
            // brief van de gemeente; "Welkom terug, Erik" als een begroeting.
            'firstName' => Str::before((string) $user->name, ' '),
        ];
    }
}
