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
    | **Er is over besloten, en het antwoord is `false`.** Dat kost niets:
    | er dráait geen SSR-server, dus er werd al niets server-side
    | gerenderd. Uitzetten haalt alleen de mislukte verbindingspoging weg
    | die elke paginaweergave deed -- lokaal en op gedeelde hosting even
    | zinloos -- en laat de config zeggen wat er werkelijk gebeurt.
    |
    | **Wat crawlers en linkvoorbeelden nodig hebben, komt ergens anders
    | vandaan.** `og:title` en `og:description` worden server-side in
    | app.blade.php gezet, uit de gegevens van de pagina. Daar is geen
    | draaiend proces voor nodig, dus dat werkt ook op gedeelde hosting.
    |
    | Komt er ooit hosting waar een proces mag blijven draaien, dan zet
    | `SSR_ENABLED=true` in `.env` hem weer aan -- samen met `build:ssr` in
    | de deploy en `inertia:start-ssr` ernaast. Zie beslissing 018 in
    | docs/decisions/README.md.
    */
    'ssr' => [
        'enabled' => (bool) env('SSR_ENABLED', false),
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
