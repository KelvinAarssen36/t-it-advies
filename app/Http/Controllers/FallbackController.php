<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * De pagina die je krijgt als een adres nergens op uitkomt.
 *
 * Dit is met opzet een **route** en niet alleen een regel in de
 * foutafhandeling, en daar zit een valkuil achter die je één keer meemaakt.
 *
 * Een 404 op een adres dat op geen enkele route past, wordt door de router
 * gegooid **voordat** de middlewaregroep `web` heeft gedraaid. Er is op dat
 * moment dus geen sessie, geen ingelogde gebruiker en geen gedeelde
 * Inertia-props. Een foutpagina die vanuit `withExceptions` wordt
 * gerenderd komt daardoor zonder zijbalk en zonder accountmenu binnen -- en
 * omdat `$request->user()` daar altijd leeg is, zag een ingelogde beheerder
 * gewoon de standaardpagina van Laravel.
 *
 * Een fallback-route staat wél in die groep. Sessie, gebruiker en gedeelde
 * props zijn hier dus gewoon beschikbaar.
 *
 * De andere foutcodes (403, 429, 500, 503) worden binnen een route gegooid,
 * dus daar is dat probleem er niet; die lopen via de `respond`-callback in
 * bootstrap/app.php.
 *
 * Zie docs/architecture/foutpaginas.md.
 */
class FallbackController extends Controller
{
    public function __invoke(Request $request): Response
    {
        /*
         * Een bezoeker die niet is ingelogd krijgt de standaardpagina. De
         * landing krijgt later een eigen variant; zie het document
         * hierboven. `abort` en geen eigen respons, zodat er één plek is
         * die bepaalt hoe dat eruitziet.
         */
        abort_if($request->user() === null, 404);

        /*
         * Wie JSON vraagt krijgt JSON. Een fallback vangt ook verzoeken van
         * de applicatie zelf af -- een fetch naar een adres dat niet meer
         * bestaat -- en daar een pagina in HTML op terugsturen levert een
         * onbegrijpelijke parseerfout op in plaats van een nette 404.
         */
        abort_if($request->expectsJson(), 404);

        /*
         * De statuscode moet blijven staan. Zonder `setStatusCode` krijg je
         * een nette pagina met een 200 erachter, en dan is het voor een
         * zoekmachine en voor een monitor een bestaande pagina.
         */
        return Inertia::render('Error', ['status' => 404])
            ->toResponse($request)
            ->setStatusCode(404);
    }
}
