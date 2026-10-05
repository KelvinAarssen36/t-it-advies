<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | Ontvangstadres contactformulier
    |--------------------------------------------------------------------------
    |
    | Waar berichten uit het contactformulier naartoe gaan.
    |
    | **Dit is het publieke adres van de klant en niet zijn inlogadres.**
    | Die twee zijn uitdrukkelijk gescheiden; welke waarvoor is staat in
    | config/site.php.
    |
    | De terugval is daarom `SITE_EMAIL` en niet meer het from-adres. Dat
    | laatste stond er eerst, en dat was fout: dan komt een bericht uit het
    | contactformulier binnen op het no-reply-adres zodra iemand
    | `MAIL_CONTACT_ADDRESS` leeg laat -- precies de postbus die niemand
    | leest.
    |
    */

    // Let op de ?: en niet de tweede parameter van env(): een variabele die
    // wel in .env staat maar leeg is, geeft een lege string terug en niet
    // null. Met env(..., $default) zou je dan naar het lege adres versturen.
    'contact_address' => env('MAIL_CONTACT_ADDRESS')
        ?: (env('SITE_EMAIL') ?: 'info@atitadvies.nl'),

    /*
    | Het afzendadres.
    |
    | Ook `info@`, en niet een no-reply op een ander domein zoals hier
    | eerst stond. Twee redenen: het domein was simpelweg verkeerd, en op
    | gedeelde hosting komt mail van een adres dat niet als echte postbus
    | bestaat eerder in de spam terecht -- SPF en DKIM horen bij een
    | bestaand adres. Zie docs/operations/deployment.md.
    |
    | Let ook hier op de `?:` en niet de tweede parameter van `env()`,
    | om precies dezelfde reden als bij `contact_address` hierboven: een
    | `MAIL_FROM_ADDRESS=` zonder waarde -- en dat is de bedoeling, want
    | dan geldt `SITE_EMAIL` -- geeft een lege string terug en geen null.
    | Met `env(..., $default)` blijft het afzendadres dus leeg, en dan
    | weigert Symfony de mail met "An email must have a From header".
    | MailLoggingTest viel daar meteen over.
    */
    'from' => [
        'address' => env('MAIL_FROM_ADDRESS')
            ?: (env('SITE_EMAIL') ?: 'info@atitadvies.nl'),
        'name' => env('MAIL_FROM_NAME') ?: env('APP_NAME', 'Laravel'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Bewaartermijn mailoverzicht
    |--------------------------------------------------------------------------
    |
    | Hoeveel dagen een regel in `mail_logs` blijft staan. Opgeruimd door de
    | geplande taak `mail:prune-logs`; zie docs/operations/onderhoudstaken.md.
    |
    | Staat los van de termijn van het beveiligingslogboek: een bounce van een
    | jaar geleden zegt niets meer, een inlogpoging van een jaar geleden wel.
    |
    */

    'log_retention_days' => (int) env('MAIL_LOG_RETENTION_DAYS', 180),

    /*
    |--------------------------------------------------------------------------
    | Hoe een mail eruitziet
    |--------------------------------------------------------------------------
    |
    | **Eén huisstijl voor álle mail die deze applicatie verstuurt.** Niet
    | alleen de bevestiging aan een bezoeker, maar ook de
    | beveiligingsmeldingen en de crashmelding: die komen allemaal bij
    | iemand binnen, en een mail die eruitziet als een standaardsjabloon
    | leest als iets dat niet van dit bedrijf komt.
    |
    | Het thema `atit` staat in resources/views/vendor/mail/html/themes/ en
    | is Laravel's eigen thema met onze kleuren erin. Bewust een kopie en
    | geen eigen sjabloon: de opbouw van een mail is uitgevochten tegen
    | twintig jaar mailprogramma's, en die strijd doen we niet over.
    |
    | Zie docs/architecture/mail-en-queues.md.
    |
    */

    'markdown' => [
        'theme' => 'atit',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
