<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * De koppen die de browser vertellen wat hij níet mag doen.
 *
 * Dit is beveiliging die je gratis krijgt van de browser, mits je het
 * vraagt. Elke kop hieronder sluit een categorie aanvallen af die je
 * anders in de applicatie zelf zou moeten afvangen -- of, vaker, zou
 * vergeten af te vangen.
 *
 * Bewust géén Content-Security-Policy hier. Die verdient een eigen
 * behandeling: Vite laadt in ontwikkeling van een andere poort, Turnstile
 * en Bunny Fonts laden van hun eigen domein, en een CSP die je half instelt
 * breekt de site zonder dat je het in de tests ziet. Zie
 * docs/security/headers.md voor wat daarvoor nodig is.
 */
class SecurityHeaders
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /*
         * Niet in een iframe. Zonder dit kan iemand onze inlogpagina in een
         * onzichtbaar frame over zijn eigen knoppen leggen en je zo op
         * dingen laten klikken die je niet ziet -- clickjacking.
         *
         * SAMEORIGIN en niet DENY: onze eigen pagina's mogen elkaar wel
         * insluiten, mocht dat ooit nodig zijn voor een voorbeeldweergave.
         */
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        /*
         * De browser mag niet zelf raden wat voor bestand hij binnenkrijgt.
         * Zonder dit kan een geüpload bestand dat wij als tekst serveren
         * door de browser alsnog als script worden uitgevoerd.
         */
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        /*
         * Bij een klik naar een andere site geven we wel het domein mee,
         * maar niet het volledige adres. Een pad als
         * /reset-password/<token> hoort niet in de logs van een derde
         * partij terecht te komen.
         */
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        /*
         * Wij vragen niet om de camera, de microfoon of de locatie, dus
         * zetten we die uit. Zou er ooit een script binnenkomen dat dat wel
         * probeert, dan weigert de browser het zonder dat iemand iets
         * merkt.
         */
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        );

        /*
         * Alleen via https, en dat onthoudt de browser twee jaar.
         *
         * Alleen buiten `local`, en alleen op een verzoek dat al beveiligd
         * is. Zet je dit op http, dan doet de browser er niets mee; zet je
         * het lokaal, dan dwingt je browser https af op *.test en kom je er
         * niet meer bij zonder je browsergegevens te wissen. Dat is een
         * middag zoeken.
         */
        if ($request->secure() && ! app()->environment('local', 'testing')) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=63072000; includeSubDomains; preload',
            );
        }

        return $response;
    }
}
