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

];
