<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Features;

/**
 * De pagina waar je bij je eerste bezoek verplicht langs moet.
 *
 * Deze route staat bewust **buiten** de middleware `two-factor.required`.
 * Zou hij erachter staan, dan stuurt de middleware je naar de pagina waar
 * hij je vervolgens weer vandaan stuurt.
 *
 * Zie docs/security/authenticatie-en-2fa.md.
 */
class TwoFactorSetupController extends Controller
{
    public function show(Request $request): Response
    {
        $user = $request->user();

        abort_if(! $user instanceof User, 403);

        // Bewust geen doorverwijzing zodra het bevestigd is. Fortify stuurt
        // je na het intypen van de code terug naar deze pagina, en dat is
        // precies het moment waarop de recovery codes getoond moeten
        // worden. Wie hier doorverwijst, slaat die stap over -- en dan
        // heeft de eigenaar geen enkele weg terug als hij zijn telefoon
        // kwijtraakt.
        return Inertia::render('auth/TwoFactorSetup', [
            'requiresConfirmation' => Features::optionEnabled(
                Features::twoFactorAuthentication(),
                'confirm',
            ),
            'twoFactorEnabled' => $user->hasEnabledTwoFactorAuthentication(),
            'twoFactorConfirmed' => $user->two_factor_confirmed_at !== null,
            'passwordConfirmed' => $this->passwordWasJustConfirmed($request),
        ]);
    }

    /**
     * Of de gebruiker zijn wachtwoord zojuist heeft bevestigd.
     *
     * Fortify vraagt daarom voordat 2FA aangezet mag worden, en onderschept
     * daarvoor het verzoek. Na het bevestigen wordt dat verzoek **niet**
     * opnieuw uitgevoerd: je komt gewoon terug op deze pagina. Zonder dit
     * gegeven ziet die er dan precies hetzelfde uit als daarvoor, en lijkt
     * het alsof je niets hebt gedaan.
     *
     * Dezelfde grens als Fortify zelf gebruikt, zodat we niet iets anders
     * "bevestigd" noemen dan de middleware even verderop.
     */
    private function passwordWasJustConfirmed(Request $request): bool
    {
        $confirmedAt = $request->session()->get('auth.password_confirmed_at');

        if (! is_int($confirmedAt)) {
            return false;
        }

        $timeout = (int) config('auth.password_timeout', 10800);

        return (time() - $confirmedAt) < $timeout;
    }

    /**
     * Doorgaan na het instellen.
     *
     * Via het laadscherm, net als na een gewone login: dat pakt de
     * onthouden bestemming op en zorgt dat je niet naar een half
     * opgebouwde pagina kijkt terwijl het portaal zijn bundel ophaalt.
     */
    public function finish(): RedirectResponse
    {
        return redirect()->route('portal.enter');
    }
}
