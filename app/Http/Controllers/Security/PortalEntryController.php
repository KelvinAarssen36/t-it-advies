<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het korte tussenscherm tussen inloggen en het portaal.
 *
 * Je ziet het ongeveer een seconde. Dat is geen vertraging om de show: het
 * portaal haalt op dat moment zijn eigen bundel op, en zonder tussenscherm
 * kijk je naar een halve pagina die zich nog aan het opbouwen is.
 *
 * De bestemming wordt **hier** gecontroleerd en niet in de browser. Hij
 * komt uit de sessie, en een bestemming die je ongecontroleerd doorgeeft is
 * precies hoe je een open omleiding bouwt.
 *
 * Zie docs/security/authenticatie-en-2fa.md.
 */
class PortalEntryController extends Controller
{
    /** Hoelang het scherm blijft staan, in milliseconden. */
    private const DURATION = 960;

    /*
     * Hier wordt géén begroeting meer gezet, en dat is met opzet.
     *
     * Dit scherm is niet alleen de weg na het inloggen: je komt er ook
     * langs als je vanaf de website terug naar het portaal gaat. Zou de
     * vlag hier staan, dan kreeg je "Welkom terug" elke keer dat je even
     * op je eigen site hebt gekeken.
     *
     * De begroeting hoort bij het inloggen, dus die wordt daar gezet --
     * in de LoginResponse in FortifyServiceProvider.
     */
    public function show(Request $request): Response
    {
        return Inertia::render('auth/PortalEntry', [
            'destination' => $this->destination($request),
            'duration' => self::DURATION,
        ]);
    }

    /**
     * Waar de bezoeker heen gaat na het laadscherm.
     *
     * Alleen een pad binnen deze applicatie. Alles met een schema, een
     * dubbele slash of een backslash erin gooien we weg en vervangen we
     * door het dashboard.
     */
    private function destination(Request $request): string
    {
        $fallback = route('dashboard', absolute: false);

        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || trim($intended) === '') {
            return $fallback;
        }

        $intended = trim($intended);

        // Eerst de host, en pas daarna het pad. Op het pad alleen toetsen is
        // niet genoeg: bij `//kwaadaardig.example/phishing` haalt parse_url
        // de host eruit en houd je een onschuldig ogend `/phishing` over,
        // terwijl een browser het als een volledig adres leest.
        $host = parse_url($intended, PHP_URL_HOST);

        if (is_string($host) && $host !== $request->getHost()) {
            return $fallback;
        }

        $path = parse_url($intended, PHP_URL_PATH);

        if (! is_string($path) || ! str_starts_with($path, '/') || str_starts_with($path, '/\\')) {
            return $fallback;
        }

        // Dit scherm als bestemming zou een rondje worden. Dat gebeurt echt:
        // wie zonder 2FA binnenkomt wordt hiervandaan naar de instelpagina
        // gestuurd, en dan staat dit adres als onthouden bestemming klaar.
        if (rtrim($path, '/') === rtrim(route('portal.enter', absolute: false), '/')) {
            return $fallback;
        }

        $query = parse_url($intended, PHP_URL_QUERY);

        return $path.(is_string($query) && $query !== '' ? '?'.$query : '');
    }
}
