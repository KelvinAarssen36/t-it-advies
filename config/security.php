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

        'thresholds' => [
            'failed_logins' => (int) env('SECURITY_ALERT_FAILED_LOGINS', 25),
            'mail_problems' => (int) env('SECURITY_ALERT_MAIL_PROBLEMS', 5),
        ],
    ],

];
