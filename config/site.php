<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Waar de eigenaar buiten deze site te vinden is
    |--------------------------------------------------------------------------
    |
    | Echte gegevens van de klant, die in elke omgeving horen te bestaan --
    | net als het account van de eigenaar in config/security.php. Geen
    | testdata, geen voorbeeld.
    |
    | **Waarom hier en niet in de database.** Het is één adres dat niet
    | verandert, en er is bewust geen beheerscherm voor: een tikfout in een
    | link naar buiten is een dode knop op de voorpagina, en dat risico weegt
    | niet op tegen het gemak van hem zelf kunnen wijzigen. Zo staat het ook
    | in docs/architecture/modules/linkedin.md.
    |
    | Komt er later een scherm voor -- en dat kan, zodra er meer van dit
    | soort links bijkomen -- dan verhuist dit naar een eigen tabel met een
    | eigen module. Tot die tijd is dit de enige bron, zodat de knop op de
    | landingspagina, de voettekst en alles wat er nog bij komt naar
    | hetzelfde adres wijzen.
    |
    */

    'linkedin' => env('SITE_LINKEDIN', 'https://www.linkedin.com/in/erik-aarssen/'),

];
