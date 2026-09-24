<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}"  @class(['dark' => ($appearance ?? 'system') == 'dark'])>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

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
                background-color: {{ $isPublicPage ? '#061626' : '#ffffff' }};
            }

            html.dark {
                background-color: #061626;
            }
        </style>

        <link rel="icon" href="/favicon.ico" sizes="any">
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
