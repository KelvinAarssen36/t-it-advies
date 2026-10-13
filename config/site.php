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

    /*
    |--------------------------------------------------------------------------
    | Het publieke e-mailadres
    |--------------------------------------------------------------------------
    |
    | **Er zijn twee adressen in dit project en ze doen iets anders.** Dat
    | onderscheid is uitdrukkelijk afgesproken en het is het soort ding dat
    | je per ongeluk door elkaar haalt, dus het staat hier met zoveel
    | woorden:
    |
    | | Adres                   | Waarvoor                                  |
    | | ----------------------- | ----------------------------------------- |
    | | `aarssen@atitadvies.nl` | **Alleen inloggen.** Het account van de eigenaar in het portaal; zie config/security.php. |
    | | `info@atitadvies.nl`    | **Al het andere.** Wat er aan contactgegevens op de website staat, en waar de berichten uit het contactformulier naartoe gaan. |
    |
    | Het inlogadres hoort dus nergens op de publieke site te staan, en dit
    | adres hoort nergens als inlog te worden gebruikt.
    |
    | Dit is de enige bron voor het publieke adres. `config/mail.php` valt er
    | op terug voor het ontvangstadres van het contactformulier, zodat die
    | twee niet uiteen kunnen gaan lopen.
    |
    | EmailAdressenTest houdt allebei de adressen vast.
    |
    */

    'email' => env('SITE_EMAIL', 'info@atitadvies.nl'),

    /*
    |--------------------------------------------------------------------------
    | De taal waarin de eigenaar zijn eigen post leest
    |--------------------------------------------------------------------------
    |
    | **Dit is niet hetzelfde als `app.locale`, en dat is geen smaakkwestie
    | maar noodzaak.** `app.locale` is de taal van het huidige verzoek:
    | `App::setLocale()` schrijft hem tijdens elk verzoek in de
    | configuratie -- zie `Application::setLocale()`, dat letterlijk
    | `config->set('app.locale', ...)` doet. Na de middleware `SetLocale`
    | geeft `config('app.locale')` dus de taal van de bézoeker terug.
    |
    | Dat heeft een echte fout opgeleverd. De melding uit het
    | contactformulier gebruikte `->locale(config('app.locale'))` om
    | Nederlands te forceren, en dat was een lege handeling: schreef een
    | Engelstalige bezoeker, dan kreeg de eigenaar een Engels onderwerp in
    | zijn eigen postvak. De test die dat moest afvangen vergeleek met
    | diezelfde waarde en kon dus niet falen.
    |
    | Deze instelling kan niet door een verzoek worden omgezet. Hij hoort
    | bij het bedrijf en niet bij de bezoeker, net als `site.timezone`
    | hieronder.
    |
    | **Gebruik hem voor élke mail die naar de eigenaar gaat** en nooit voor
    | iets dat een bezoeker leest. De interne mails zetten hem zelf in hun
    | constructor, zodat een aanroeper hem niet kan vergeten; zie
    | docs/architecture/mail-en-queues.md.
    |
    */

    'locale' => env('SITE_LOCALE', 'nl'),

    /*
    |--------------------------------------------------------------------------
    | De tijdzone waarin een "dag" wordt geteld
    |--------------------------------------------------------------------------
    |
    | **Dit is niet hetzelfde als `app.timezone`, en dat is met opzet.** De
    | applicatie rekent intern in UTC -- dat is de verstandige keuze voor
    | tijdstempels in de database. Maar een bezoekcijfer per dag hoort te
    | lopen van middernacht tot middernacht in de tijd van de eigenaar, niet
    | van 02:00 tot 02:00.
    |
    | Zonder deze instelling zou een bezoek van 23:30 op maandag bij de
    | cijfers van dinsdag terechtkomen, en dat is precies het soort fout dat
    | niemand ziet maar dat elk cijfer een beetje scheef zet.
    |
    | De klok op het dashboard gebruikt dit niet: die volgt de persoonlijke
    | voorkeur van de gebruiker uit `DashboardTimezone`. Dit gaat over het
    | bedrijf en niet over wie er kijkt.
    |
    | Zie docs/architecture/bezoekcijfers.md.
    |
    */

    'timezone' => env('SITE_TIMEZONE', 'Europe/Amsterdam'),

    /*
    |--------------------------------------------------------------------------
    | Het contactformulier
    |--------------------------------------------------------------------------
    |
    | **Hoe lang een binnengekomen aanvraag bewaard blijft.** Dit is de
    | enige bewaartermijn in dit project die over inhoud van een bezoeker
    | gaat en niet over een logboek, en daarom verdient hij uitleg.
    |
    | Een aanvraag bevat de naam, het e-mailadres en het bericht van iemand
    | die ons schreef. Die bewaren we omdat de eigenaar moet kunnen
    | terugzoeken wie hem wanneer benaderde -- een jaar is ruim genoeg om
    | een gesprek terug te vinden en kort genoeg om uit te leggen. Het is
    | dezelfde termijn als het beveiligingslogboek, zodat er niet nog een
    | getal bij komt dat niemand onthoudt.
    |
    | Deze termijn staat **in de privacyverklaring**. Verander je hem hier,
    | dan verandert die tekst mee: `PrivacyController` leest deze waarde
    | uit, net als het scherm Beheer → Juridisch. Zie
    | docs/architecture/modules/contact.md.
    |
    | Opgeruimd door `PruneContactSubmissions`; zie routes/console.php.
    |
    */

    'contact' => [
        'retention_days' => (int) env('CONTACT_RETENTION_DAYS', 365),
    ],

];
