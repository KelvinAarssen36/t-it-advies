<?php

namespace App\Http\Responses;

use App\Enums\SecurityEventType;
use App\Models\User;
use App\Support\Security\SecurityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse as PasskeyLoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wat er gebeurt nadat een passkey is goedgekeurd.
 *
 * Normaal: doorlopen naar het portaal. Heeft de eigenaar de extra stap
 * aangezet, dan eerst nog zijn authenticator-code.
 *
 * ## Waarom dit zo wordt opgelost
 *
 * `PasskeyLoginController::store()` heeft de gebruiker op dit punt al
 * ingelogd; dat zit in het pakket en daar komen we niet tussen. We maken
 * dat hier ongedaan en zetten hem in de **challenge van Fortify**, met
 * `login.id` in de sessie -- precies de toestand waarin een gewone
 * wachtwoordlogin met 2FA ook belandt.
 *
 * Dat is met opzet, en niet de kortste weg. De kortste weg zou zijn: laat
 * hem ingelogd en hou met middleware elk scherm dicht tot de code klopt.
 * Dan bestaat er een toestand waarin iemand is ingelogd maar nog niet
 * bevestigd, en is elke route die je vergeet een gat. Hier bestaat die
 * toestand niet: hij ís niet ingelogd, en het scherm, de recovery codes
 * en de begrenzing zijn allemaal die van Fortify.
 *
 * `logoutCurrentDevice()` en niet `logout()`: die eerste laat het
 * remember-token van ándere apparaten met rust. Anders logt een geslaagde
 * passkey je op je telefoon uit.
 *
 * Zie docs/security/extra-stap-na-een-passkey.md.
 */
class PasskeyLoginResponse implements PasskeyLoginResponseContract
{
    public function __construct(private readonly SecurityLogger $logboek) {}

    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $gebruiker = Auth::user();

        if (! $gebruiker instanceof User || ! $gebruiker->vraagtExtraCodeNaEenPasskey()) {
            return $this->naarHetLaadscherm($request);
        }

        $onthouden = $request->boolean('remember');
        $id = $gebruiker->getAuthIdentifier();

        Auth::guard((string) config('passkeys.guard'))->logoutCurrentDevice();

        /*
         * Pas ná het uitloggen, want `logoutCurrentDevice()` haalt de
         * gebruiker uit de sessie -- en deze twee sleutels moeten daar
         * juist blijven staan. Fortify leest ze in TwoFactorLoginRequest.
         */
        $request->session()->put([
            'login.id' => $id,
            'login.remember' => $onthouden,
            'login.via_passkey' => true,
        ]);

        $this->logboek->success(SecurityEventType::PasskeyStepChallenged, $gebruiker);

        $doel = route('two-factor.login');

        /*
         * De browser doet dit met `fetch`, dus het pakket antwoordt in
         * JSON met een adres erin. Zouden we hier een gewone omleiding
         * teruggeven, dan zou het script die volgen en de HTML van het
         * challenge-scherm in de console zetten in plaats van erheen te
         * gaan.
         */
        if ($request->wantsJson()) {
            return new JsonResponse(['redirect' => $doel], 200);
        }

        return new RedirectResponse($doel);
    }

    /**
     * Hetzelfde onthaal als na een wachtwoordlogin.
     *
     * Het antwoord van het pakket stuurt je naar `passkeys.redirect`, en
     * dat staat standaard op `/` -- de publieke website. Verder zet het
     * niets in de sessie. Daardoor miste een passkey-login drie dingen
     * die bij elke andere login wél gebeuren:
     *
     * - de eenmalige begroeting "Welkom terug";
     * - het laadscherm, dat het portaal zijn bundel laat ophalen;
     * - de onthouden bestemming, die het laadscherm uitleest.
     *
     * **`url.intended` blijft hier met rust.** `redirect()->intended()`
     * zou hem opmaken, en dan komt de eigenaar op het dashboard in plaats
     * van waar hij heen wilde. Het laadscherm leest hem zelf; zie
     * PortalEntryController::destination().
     *
     * **Wat hier bewust níet gebeurt**, anders dan bij een wachtwoord, is
     * `auth.password_confirmed_at` zetten. Een passkey bewijst dat je het
     * apparaat hebt, niet dat je het wachtwoord kent -- en dat laatste is
     * precies wat die bevestiging wil weten. Het scherm Beveiliging vraagt
     * er na een passkey dus nog om. Zie FortifyServiceProvider.
     */
    private function naarHetLaadscherm(Request $request): Response
    {
        $request->session()->put('portal.welcome', true);

        $doel = route('portal.enter');

        if ($request->wantsJson()) {
            return new JsonResponse(['redirect' => $doel], 200);
        }

        return new RedirectResponse($doel);
    }
}
