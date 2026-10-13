<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
        // Ondertekeningsgeheim van de webhook (Svix). Zonder deze waarde
        // weigert de endpoint alles; zie ResendWebhookController.
        'webhook_secret' => env('MAIL_WEBHOOK_SECRET'),
    ],

    /*
     * Cloudflare Turnstile. De site key is publiek en gaat via Inertia naar
     * de frontend; de secret key blijft op de server.
     */
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret' => env('TURNSTILE_SECRET_KEY'),
        'verify_url' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'timeout' => 5,
    ],

    /*
     * De knop "Vertaal automatisch" in het portaal, via MyMemory.
     *
     * Er is bewust geen sleutel en geen account: de API staat open. Zie
     * docs/architecture/automatisch-vertalen.md voor waarom die eis
     * zwaarder woog dan de laatste procenten vertaalkwaliteit.
     */
    'translate' => [
        /*
         * Uitzetten laat de knop uit het scherm verdwijnen en de route een
         * 404 geven. In tests staat hij uit, zodat geen enkele test per
         * ongeluk het internet op gaat.
         */
        'enabled' => (bool) env('TRANSLATE_ENABLED', true),

        'endpoint' => 'https://api.mymemory.translated.net/get',

        /*
         * Optioneel. Een adres hierin verhoogt het dagtegoed van 5.000
         * naar 50.000 tekens; het gaat als parameter mee en er hoort geen
         * aanmelding bij. Leeg laten mag.
         */
        'email' => env('TRANSLATE_EMAIL'),

        'timeout' => (int) env('TRANSLATE_TIMEOUT', 6),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
