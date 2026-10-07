<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server Side Rendering
    |--------------------------------------------------------------------------
    |
    | These options configures if and how Inertia uses Server Side Rendering
    | to pre-render each initial request made to your application's pages
    | so that server rendered HTML is delivered for the user's browser.
    |
    | See: https://inertiajs.com/server-side-rendering
    |
    */

    /*
    | **Hier stond `true` vast, en dat was misleidend.** Er wordt namelijk
    | nergens server-side gerenderd: `npm run build` bouwt de SSR-bundel
    | niet -- dat doet alleen `npm run build:ssr` -- en de deploy start er
    | geen proces voor. Inertia valt dan stil terug op opbouwen in de
    | browser, dus de site werkt; maar elke paginaweergave doet eerst een
    | verbindingspoging naar de poort hieronder die mislukt, en dat kost bij
    | elk bezoek tijd voor niets.
    |
    | Nagemeten dat SSR het zelf wél doet: met `build:ssr` en
    | `php artisan inertia:start-ssr` komt de volledige pagina uit de
    | server. Het is dus geen kapotte instelling maar een onafgemaakte.
    |
    | **De waarde blijft `true` zolang er niet over besloten is.** Dit is
    | een keuze tussen hosting waar een proces mag draaien en accepteren
    | dat de site in de browser wordt opgebouwd, en die keuze hoort niet in
    | een configuratiebestand te worden gemaakt. Wat er nu wél is, is de
    | schakelaar: `SSR_ENABLED=false` in `.env` zet hem uit zonder aan de
    | code te komen. Zie docs/openstaand.md.
    */
    'ssr' => [
        'enabled' => (bool) env('SSR_ENABLED', true),
        'url' => env('SSR_URL', 'http://127.0.0.1:13714'),
        // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),

    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | These options configure how Inertia discovers page components on the
    | filesystem. The paths and extensions are used to locate components
    | when rendering responses and during testing assertions.
    |
    */

    'pages' => [

        'paths' => [
            resource_path('js/pages'),
        ],

        'extensions' => [
            'js',
            'jsx',
            'svelte',
            'ts',
            'tsx',
            'vue',
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Testing
    |--------------------------------------------------------------------------
    |
    | The values described here are used to locate Inertia components on the
    | filesystem. For instance, when using `assertInertia`, the assertion
    | attempts to locate the component as a file relative to the paths.
    |
    */

    'testing' => [

        'ensure_pages_exist' => true,

    ],

];
