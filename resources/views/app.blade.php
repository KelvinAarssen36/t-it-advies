<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" @class(['dark' => ($appearance ?? 'dark') !== 'light'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{--
            De veelgestelde vragen als structuurdata.

            Staat er alleen op de landingspagina, en alleen als er vragen
            zijn; HomeController zet hem klaar via `withViewData`. Het blok
            op de pagina bladert per zes, maar álle vragen staan in de HTML
            -- dit zegt er bovendien expliciet bij wát die tekst is.

            `{!! !!}` en geen `{{ }}`: dit is JSON en geen HTML-tekst. Dat
            is veilig omdat `vragenBriefje()` met `JSON_HEX_TAG` codeert,
            waardoor elke `<` een `<` wordt en een `</script>` in een
            antwoord de tag niet kan afbreken. Haal die vlag daar dus niet
            weg.

            Zie docs/architecture/modules/faq.md.
        --}}
        @isset($vragenBriefje)
            <script type="application/ld+json">{!! $vragenBriefje !!}</script>
        @endisset

        {{--
            Hier stond een script dat de voorkeur van het besturingssysteem
            uitlas. Dat is niet meer nodig: er zijn nog twee thema's, licht
            en donker, en welke het is staat hierboven al op <html> op basis
            van de cookie. Zie docs/architecture/huisstijl-en-kleuren.md.
        --}}

        @php
            // De publieke site staat altijd in het donkere thema; zie
            // PublicLayout.vue en docs/architecture/huisstijl-en-kleuren.md.
            $isPublicPage = $page['component'] === 'Welcome'
                || str_starts_with($page['component'], 'public/');
        @endphp

        {{--
            De achtergrondkleur staat hier inline, vóór de stylesheet, zodat je
            geen flits van een verkeerde kleur ziet. Voor een openbare pagina is
            dat altijd Midnight Navy: die staat los van de voorkeur van de
            bezoeker, dus meebewegen met .dark zou juist een witte flits geven.
            Kleuren: docs/architecture/huisstijl-en-kleuren.md
        --}}
        <style>
            html {
                background-color: {{ $isPublicPage || ($appearance ?? 'dark') !== 'light' ? '#061626' : '#ffffff' }};
            }

            html.dark {
                background-color: #061626;
            }
        </style>

        {{--
            Het icoon staat ook op /favicon.ico -- de plek waar browsers en
            bots hem uit zichzelf zoeken -- maar we verwijzen naar de kopie in
            /images. Valet Linux heeft in zijn nginx-config een exacte
            location voor /favicon.ico die van de rewrite naar server.php
            wint; het bestand komt dan met status 404 binnen en een browser
            weigert een favicon met een foutcode. Op productie speelt dat niet.
            Zie docs/architecture/frontend-en-animatie.md.
        --}}
        <link rel="icon" href="/images/favicon.ico" sizes="any">
        <link rel="apple-touch-icon" href="/apple-touch-icon.png">

        {{--
            Wat sociale media tonen bij een gedeelde link. De titel komt uit
            <title>, die Inertia per pagina zet. Een omschrijving per pagina
            hoort bij de SEO-ronde; dit is de bodem waar niets ontbreekt.
        --}}
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:type" content="website">
        <meta property="og:image" content="{{ url('/images/og-afbeelding.jpg') }}">
        <meta name="twitter:card" content="summary_large_image">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.ts', "resources/js/pages/{$page['component']}.vue"])
        <x-inertia::head>
            <title>{{ config('app.name', 'Laravel') }}</title>
        </x-inertia::head>
    </head>
    <body class="font-sans antialiased">
        <x-inertia::app />
    </body>
</html>
