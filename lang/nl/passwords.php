<?php

/*
|--------------------------------------------------------------------------
| Meldingen bij een wachtwoord opnieuw instellen
|--------------------------------------------------------------------------
|
| De Engelse zinnen van Laravel naar het Nederlands; zie de toelichting in
| validation.php en docs/architecture/vertalingen.md.
|
| **Let op bij `user`.** Fortify geeft deze melding echt terug wanneer er bij
| een e-mailadres geen account hoort -- nagekeken in
| `PasswordResetLinkController::store()`, dat bij een onbekend adres een
| `FailedPasswordResetLinkRequestResponse` teruggeeft en geen bevestiging.
| Via het formulier "wachtwoord vergeten" is dus te zien of een adres
| bestaat. Bij dit portaal is dat één adres dat ook in de documentatie
| staat, dus er valt niets te ontdekken, maar vertaal deze zin niet
| vriendelijker dan hij is: hij zegt echt iets over het account.
|
| Zie docs/security/authenticatie-en-2fa.md.
|
*/

return [

    'reset' => 'Je wachtwoord is opnieuw ingesteld.',
    'sent' => 'We hebben je een link gestuurd om je wachtwoord opnieuw in te stellen.',
    'throttled' => 'Even wachten voordat je het opnieuw probeert.',
    'token' => 'Deze link om je wachtwoord opnieuw in te stellen is niet meer geldig.',
    'user' => 'Bij dit e-mailadres hoort geen account.',

];
