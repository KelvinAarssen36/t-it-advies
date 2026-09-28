<?php

namespace App\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het korte tussenscherm van het portaal terug naar de website.
 *
 * De spiegel van PortalEntryController, en om dezelfde reden: de publieke
 * site heeft een eigen bundel met de hele animatielaag erin, die het portaal
 * nooit ophaalt. Zonder tussenscherm kijk je daar even tegen een pagina aan
 * die zich nog aan het opbouwen is.
 *
 * Er is hier géén open omleiding mogelijk, en dat is met opzet: de
 * bestemming staat vast op de homepage en komt niet uit de sessie of uit een
 * parameter. Dit scherm hoeft nergens anders heen te kunnen.
 *
 * Zie docs/architecture/frontend-en-animatie.md.
 */
class SiteEntryController extends Controller
{
    /**
     * Hoelang het scherm blijft staan, in milliseconden.
     *
     * Korter dan bij het binnenkomen. Naar binnen gaan is een moment -- je
     * logt in, je komt ergens aan -- maar even naar je eigen site kijken is
     * dat niet, en dan is elke tel die je langer wacht er een te veel.
     */
    private const DURATION = 720;

    public function __invoke(): Response
    {
        return Inertia::render('portal/SiteEntry', [
            'destination' => route('home', absolute: false),
            'duration' => self::DURATION,
        ]);
    }
}
