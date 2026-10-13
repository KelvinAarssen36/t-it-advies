<?php

/*
|--------------------------------------------------------------------------
| Meldingen bij het inloggen
|--------------------------------------------------------------------------
|
| De Engelse zinnen van Laravel naar het Nederlands; zie de toelichting in
| validation.php en docs/architecture/vertalingen.md.
|
| Dit is het scherm waar de eigenaar elke dag binnenkomt, en hier stond
| "These credentials do not match our records." Dat is niet alleen Engels,
| het is ook de zin die het minst behulpzaam is van alle meldingen in dit
| portaal.
|
| **Het blijft met opzet vaag over wát er niet klopt.** Niet "dit adres
| bestaat niet": dan vertel je iemand die adressen aan het uitproberen is
| welke er wél zijn. Zie docs/security/authenticatie-en-2fa.md.
|
*/

return [

    'failed' => 'Deze combinatie van e-mailadres en wachtwoord kennen we niet.',
    'password' => 'Het wachtwoord is onjuist.',
    'throttle' => 'Te veel pogingen. Probeer het over :seconds seconden opnieuw.',

];
