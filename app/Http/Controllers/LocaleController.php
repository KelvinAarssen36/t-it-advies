<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Wisselt van taal.
 *
 * Eén route voor alle drie de knoppen -- die in het accountmenu, die in de
 * instellingen en die op de publieke site. Ze schrijven allemaal naar
 * dezelfde twee bronnen, zodat het niet uitmaakt waar iemand wisselt.
 *
 * Zie docs/architecture/vertalingen.md.
 */
class LocaleController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(
            array_key_exists($locale, (array) config('app.available_locales')),
            404,
        );

        // Altijd in de sessie: dat geldt voor deze browser, ook voor een
        // bezoeker zonder account.
        $request->session()->put('locale', $locale);

        // En ook in het profiel, als er iemand is ingelogd. Wisselt de
        // eigenaar op de publieke site, dan staat het portaal er straks ook
        // in -- dat is het detail dat de twee helften verbindt.
        $user = $request->user();

        if ($user instanceof User) {
            $user->forceFill(['locale' => $locale])->save();
        }

        // Terug naar waar je was. Vertaalde tekst wordt bij het renderen
        // bepaald, dus de pagina moet hoe dan ook opnieuw opgebouwd worden;
        // dan kun je net zo goed op je eigen plek blijven.
        return back();
    }
}
