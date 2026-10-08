<?php

namespace App\Http\Controllers\Settings;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Security\Authenticator;
use App\Support\Security\SecurityLogger;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * De extra stap na een passkey aan- of uitzetten.
 *
 * **De authenticator-code zit in dit verzoek**, en niet in de middleware
 * `2fa.confirm` zoals bij de meeste gevoelige acties. Dat is een
 * weloverwogen afwijking.
 *
 * Die middleware kan een PUT niet onthouden. Hij onthoudt alleen de
 * pagina waar je vandaan kwam, stuurt je naar het codescherm, en gooit je
 * verzoek weg -- je komt terug op een scherm waar niets is veranderd en
 * moet de schakelaar nog een keer omzetten. Bij een knop in een lijst valt
 * dat te verdedigen; bij een schuifje dat zichtbaar terugspringt leest het
 * als een storing.
 *
 * De controle zelf is níet opnieuw geschreven: die staat in
 * App\Support\Security\Authenticator, en het aparte codescherm gebruikt
 * precies dezelfde klasse.
 *
 * **Een code voor allebei de kanten**, en de belangrijkste is uitzetten --
 * dat haalt een slot weg. Zou alleen aanzetten een code vragen, dan kan
 * wie achter een open scherm gaat zitten de extra stap er gewoon af halen
 * en daarna rustig met de passkey naar binnen.
 *
 * Zie docs/security/extra-stap-na-een-passkey.md.
 */
class PasskeyStepController extends Controller
{
    public function update(
        Request $request,
        Authenticator $authenticator,
        SecurityLogger $logboek,
    ): RedirectResponse {
        /** @var User $gebruiker */
        $gebruiker = $request->user();

        $velden = $request->validate([
            'aan' => ['required', 'boolean'],
            'code' => ['required', 'string'],
        ]);

        $aan = (bool) $velden['aan'];

        /*
         * Zonder bevestigde tweestapsverificatie is er geen code om te
         * controleren, en zou de schakelaar iets beloven wat bij het
         * inloggen stilletjes wordt overgeslagen. Eerst deze, want
         * `bevestig()` hieronder zou anders op een leeg geheim stuklopen
         * met een melding die nergens over gaat.
         */
        if ($gebruiker->two_factor_confirmed_at === null) {
            throw ValidationException::withMessages([
                'aan' => __('Zet eerst tweestapsverificatie aan; anders is er geen code om te vragen.'),
            ]);
        }

        $authenticator->bevestig($gebruiker, (string) $velden['code']);

        if ($gebruiker->passkey_requires_two_factor === $aan) {
            return back();
        }

        $gebruiker->forceFill(['passkey_requires_two_factor' => $aan])->save();

        $logboek->success(SecurityEventType::PasskeyStepChanged, $gebruiker, [
            'aan' => $aan,
        ]);

        Toast::bijgewerkt(
            $aan
                ? __('De extra stap staat aan.')
                : __('De extra stap staat uit.'),
            (string) ($aan
                ? __('Na een passkey vraagt het portaal voortaan ook je authenticator-code.')
                : __('Met een passkey ben je voortaan meteen binnen.')),
        );

        return back();
    }
}
