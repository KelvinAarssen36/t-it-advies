<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Gevoelige acties
    |--------------------------------------------------------------------------
    |
    | Een gevoelige actie vraagt altijd om een verse authenticator-code, ook
    | als de gebruiker al is ingelogd. Recovery codes worden hier bewust NIET
    | geaccepteerd: die zijn alleen bedoeld om weer binnen te komen wanneer je
    | je authenticator kwijt bent, niet om extra rechten te krijgen.
    |
    | 'confirmation_ttl' is het aantal seconden dat een bevestiging geldig
    | blijft binnen de sessie. Kort houden; dit is het hele punt van de check.
    |
    */

    'sensitive_actions' => [
        'confirmation_ttl' => (int) env('SENSITIVE_ACTION_TTL', 900),
        'session_key' => 'security.sensitive_action_confirmed_at',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security logging
    |--------------------------------------------------------------------------
    |
    | Beveiligingspogingen worden vastgelegd in de tabel `security_events`.
    | De sleutels hieronder worden altijd uit de context gestript voordat er
    | ook maar iets wordt weggeschreven. Zie docs/security/logging.md.
    |
    */

    'logging' => [
        'retention_days' => (int) env('SECURITY_LOG_RETENTION_DAYS', 365),

        /*
         * Het activiteitenlogboek heeft een eigen termijn. Bij onderzoek
         * naar een inbraak wil je maanden terug kunnen kijken; bij "wat heb
         * ik vorige maand aan die pagina veranderd" is een jaar ruim
         * voldoende en daarna is het ballast.
         */
        'activity_retention_days' => (int) env('ACTIVITY_LOG_RETENTION_DAYS', 365),

        'redacted_keys' => [
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'code',
            'otp',
            'one_time_password',
            'totp',
            'recovery_code',
            'recovery_codes',
            'secret',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'token',
            'api_token',
            'access_token',
            'remember_token',
            'authorization',
            'cf-turnstile-response',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Tweestapsverificatie verplicht
    |--------------------------------------------------------------------------
    |
    | Staat dit aan, dan komt niemand het portaal in vóórdat 2FA is
    | bevestigd. Er is geen knop die je kunt overslaan: bij het eerste
    | bezoek word je naar de instelpagina gestuurd en kom je daar pas vanaf
    | met een werkende code.
    |
    | In productie staat het vanzelf aan, ook als niemand eraan denkt.
    | Lokaal kun je het uitzetten, bijvoorbeeld om een testbrowser erlangs
    | te krijgen.
    |
    | Let op: dit stuurt op `two_factor_confirmed_at` en niet op het bestaan
    | van een geheim. Wie de instelpagina opent en afhaakt vóór het intypen
    | van de code heeft wel een geheim maar is niet beveiligd.
    |
    */

    'two_factor' => [
        'required' => (bool) env('PORTAL_TWO_FACTOR_REQUIRED', env('APP_ENV') === 'production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Het account van de eigenaar
    |--------------------------------------------------------------------------
    |
    | Deze applicatie heeft één gebruiker. Dat account wordt door
    | PortalAccountSeeder aangemaakt en hoort er in elke omgeving te zijn --
    | het is geen testdata.
    |
    | Naam en e-mailadres staan hier omdat ze geen geheim zijn. Het
    | wachtwoord komt uitsluitend uit .env: een wachtwoord in een bestand dat
    | in git staat is geen wachtwoord meer. Zie AGENTS.md, regel 2.
    |
    | De seeder werkt een bestaand account nooit bij. Zou hij dat wel doen,
    | dan zet elke deploy het wachtwoord terug dat in .env staat, ook als de
    | eigenaar het inmiddels zelf heeft gewijzigd.
    |
    */

    /*
    | **Dit adres is alleen om in te loggen.** Het hoort nergens op de
    | publieke site te staan en er gaat geen post naartoe; daarvoor is
    | `info@atitadvies.nl`. Welk adres waarvoor is staat uitgeschreven in
    | config/site.php, en EmailAdressenTest houdt het vast.
    */
    'portal_account' => [
        'name' => env('PORTAL_ACCOUNT_NAME', 'Erik Aarssen'),
        'email' => env('PORTAL_ACCOUNT_EMAIL', 'aarssen@atitadvies.nl'),
        'password' => env('PORTAL_ACCOUNT_PASSWORD'),
        'role' => 'admin',
    ],

    /*
    |--------------------------------------------------------------------------
    | Alarmering
    |--------------------------------------------------------------------------
    |
    | Het beveiligingslogboek is alleen nuttig als iemand kijkt. De geplande
    | taak `security:report` kijkt voor je en stuurt een mail zodra het aantal
    | mislukte pogingen of mailproblemen binnen het venster boven de drempel
    | komt.
    |
    | 'cooldown_minutes' voorkomt dat een aanval die een uur duurt ook een uur
    | lang elke keer opnieuw mailt. Na een melding blijft hetzelfde soort
    | melding zo lang stil; in het logboek staat intussen alles gewoon door.
    |
    | Zonder 'address' verstuurt de taak niets. Dat is geen storing maar de
    | keuze om geen mail naar een half ingevuld adres te sturen.
    |
    */

    'alerts' => [
        'address' => env('SECURITY_ALERT_ADDRESS'),
        'window_minutes' => (int) env('SECURITY_ALERT_WINDOW_MINUTES', 60),
        'cooldown_minutes' => (int) env('SECURITY_ALERT_COOLDOWN_MINUTES', 180),

        /*
         * Een eigen, kortere afkoeltijd voor crashmeldingen. Een kapotte
         * pagina wil je binnen het halfuur weten; een piek in mislukte
         * logins mag best drie uur wachten.
         */
        'crash_cooldown_minutes' => (int) env('CRASH_ALERT_COOLDOWN_MINUTES', 30),

        'thresholds' => [
            'failed_logins' => (int) env('SECURITY_ALERT_FAILED_LOGINS', 25),
            'mail_problems' => (int) env('SECURITY_ALERT_MAIL_PROBLEMS', 5),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Vertrouwde proxies
    |--------------------------------------------------------------------------
    |
    | **Standaard leeg, en dat is de veilige stand.** Staat er geen proxy
    | voor de applicatie, dan is `REMOTE_ADDR` het echte adres van de
    | bezoeker en hoeft er niets vertrouwd te worden.
    |
    | Komt er wél een proxy voor -- Cloudflare, een loadbalancer -- dan staat
    | het echte adres in de kop `X-Forwarded-For` en moet die proxy hier
    | genoemd worden. Twee dingen gaan anders mis, en allebei stil:
    |
    | 1. **Zonder deze instelling** krijgt elke bezoeker het adres van de
    |    proxy. In het beveiligingslogboek staat dan één adres voor alle
    |    pogingen, de snelheidsgrenzen gelden voor iedereen samen, en de
    |    bezoekcijfers zien de hele wereld als één bezoeker per dag.
    | 2. **Met `*` erin** vertrouw je een kop die de afzender zelf kan
    |    verzinnen. Dan kiest iedereen zijn eigen IP-adres, en zijn het
    |    logboek en die grenzen niets meer waard.
    |
    | Vul dus de adressen van de proxy in, gescheiden door komma's -- niet
    | `*`. Zie docs/operations/deployment.md.
    |
    */

    'trusted_proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '')),
    ))),

];
