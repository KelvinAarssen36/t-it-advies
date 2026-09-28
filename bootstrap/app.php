<?php

use App\Http\Middleware\EnsureTwoFactorIsConfigured;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RequireTwoFactorConfirmation;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use App\Support\Security\CrashReporter;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        /*
         * De koppen die de browser vertellen wat hij niet mag. Globaal en
         * niet op de webgroep: ze horen ook op een JSON-antwoord, op een
         * webhook en op een foutpagina te staan -- juist daar, want dat
         * zijn de antwoorden die buiten de gewone stroom vallen.
         */
        $middleware->append(SecurityHeaders::class);

        // Webhooks komen van servers zonder sessie of CSRF-token. Ze
        // beveiligen zich met een handtekening; zie SvixSignature.
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
        ]);

        // AddLinkHeadersForPreloadedAssets staat hier bewust NIET meer bij.
        //
        // Die middleware zet elk voorgeladen bestand in één grote
        // `Link:`-header. Die groeit mee met het aantal chunks, en zodra hij
        // over de headerbuffer van nginx heen gaat krijg je een 502 met
        // "upstream sent too big header" -- een fout die je pas ziet als de
        // applicatie groot genoeg is, en niet in de tests.
        //
        // Vite zet dezelfde voorladers al als <link rel="modulepreload"> in
        // de pagina zelf, dus we verliezen er niets mee.
        // SetLocale als eerste van onze eigen middleware, maar wel ná de
        // groep van Laravel zelf: hij leest de sessie en de ingelogde
        // gebruiker, en die bestaan pas nadat StartSession heeft gedraaid.
        // Met `prepend` zou hij vóór StartSession komen en op elke pagina
        // een 500 geven.
        //
        // Wel vóór HandleInertiaRequests, want die deelt de taal met de
        // frontend en moet dus weten wat het geworden is.
        $middleware->web(append: [
            SetLocale::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
        ]);

        // '2fa.confirm' zet je op een route die iets doet wat je niet wilt
        // terugdraaien: die vraagt dan om een verse code.
        //
        // 'two-factor.required' is iets anders: die houdt het hele portaal
        // dicht tot 2FA überhaupt is ingesteld. Zet hem op de routegroepen
        // van het portaal en niet globaal, anders loopt hij ook over het
        // inlogscherm en de foutpagina's.
        $middleware->alias([
            '2fa.confirm' => RequireTwoFactorConfirmation::class,
            'two-factor.required' => EnsureTwoFactorIsConfigured::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        /*
         * Meld het als de applicatie omvalt. Zonder dit staat een 500
         * alleen in laravel.log, en daar kijkt niemand in tot de klant
         * belt. Zie App\Support\Security\CrashReporter voor de grenzen --
         * niet lokaal, niet bij een 404, en met een afkoeltijd.
         */
        $exceptions->report(function (Throwable $exception) {
            app(CrashReporter::class)->report($exception, request());
        });

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->is('webhooks/*') || $request->expectsJson(),
        );

        /*
         * De foutpagina van het portaal.
         *
         * Alleen voor wie is ingelogd. Een bezoeker die op de landing een
         * verkeerde link volgt krijgt voorlopig de standaardpagina van
         * Laravel; die kant krijgt later een eigen variant, en die hoort er
         * anders uit te zien.
         *
         * 419 staat er bewust niet bij: dat is een verlopen CSRF-token, en
         * Inertia vangt die zelf af met een verzoek om de pagina te
         * herladen. Daar een eigen scherm overheen leggen maakt het alleen
         * maar verwarrender.
         *
         * En bij `APP_DEBUG` blijft een 500 de foutpagina van Laravel, want
         * daar staat in wát er misging. Een nette "er ging iets mis" is
         * precies wat je dan níet wilt zien.
         */
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            $eigenPagina = [403, 404, 429, 500, 503];

            if ($status === 500 && config('app.debug')) {
                return $response;
            }

            if (! in_array($status, $eigenPagina, true)) {
                return $response;
            }

            if ($request->user() === null || $request->expectsJson()) {
                return $response;
            }

            return Inertia::render('Error', ['status' => $status])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
