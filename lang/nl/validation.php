<?php

/*
|--------------------------------------------------------------------------
| Validatiemeldingen in het Nederlands
|--------------------------------------------------------------------------
|
| **Dit is de ene richting, en lang/en.json is de andere.** De afspraak
| staat in docs/architecture/vertalingen.md en is kort:
|
|   lang/en.json   onze Nederlandse zinnen  ->  Engels
|   lang/nl/       de Engelse zinnen van Laravel  ->  Nederlands
|
| Een zin die wij zelf schrijven hoort dus nooit in dit bestand.
|
| Zonder dit bestand kreeg een bezoeker die een verplicht veld leeg liet
| "The telefoonnummer field is required." te zien: een Engelse zin met een
| Nederlandse veldnaam erin, omdat de taal `nl` is maar Laravel alleen
| Engelse meldingen meelevert en dus terugvalt op `en`.
|
| **De lijst is met opzet niet compleet.** Laravel heeft ruim honderd
| regels; hier staan de regels die dit project echt gebruikt. De terugval
| werkt per sleutel, dus een regel die hier ontbreekt geeft gewoon weer de
| Engelse zin -- geen fout, en later aanvullen kan zonder risico. Gebruik je
| in een nieuwe FormRequest een regel die hier nog niet staat, zet hem er
| dan bij.
|
| **De veldnamen staan hier niet** maar in `attributes()` van elke
| FormRequest, in de woorden die de klant of de bezoeker kent ("naam",
| "onderwerp van de mail"). Daarom zijn deze zinnen zo geschreven dat ze
| werken met een kale, kleine letter ervoor: ":attribute" is "naam" en niet
| "Je naam".
|
*/

return [

    // Aanwezigheid.
    'required' => 'Vul :attribute in.',
    'required_without' => 'Vul :attribute in als :values leeg is.',
    'filled' => 'Vul :attribute in.',
    'present' => 'Stuur :attribute mee.',
    'confirmed' => 'De bevestiging van :attribute klopt niet.',
    'same' => 'Vul bij :attribute hetzelfde in als bij :other.',

    // Soort waarde.
    'string' => 'Vul tekst in bij :attribute.',
    'integer' => 'Vul een heel getal in bij :attribute.',
    'numeric' => 'Vul een getal in bij :attribute.',
    'boolean' => 'Kies ja of nee bij :attribute.',
    'array' => 'Stuur een lijst mee bij :attribute.',
    'email' => 'Vul een geldig e-mailadres in.',
    'url' => 'Vul een geldige link in bij :attribute.',
    'date' => 'Vul een geldige datum in bij :attribute.',
    'after' => 'Kies bij :attribute een datum na :date.',
    'regex' => 'De opmaak van :attribute klopt niet.',

    // Een keuze uit een lijst. Laravel gebruikt voor `in` en `exists`
    // dezelfde zin, en dat is hier ook goed: voor wie het formulier invult
    // is het verschil tussen "bestaat niet" en "mag niet" er niet.
    'in' => 'Dit is geen geldige keuze bij :attribute.',
    'exists' => 'Dit is geen geldige keuze bij :attribute.',

    // Bestanden.
    'file' => 'Kies een bestand bij :attribute.',
    'image' => 'Kies een afbeelding bij :attribute.',
    'mimes' => 'Kies bij :attribute een bestand van het type: :values.',
    'uploaded' => 'Het uploaden van :attribute is niet gelukt. Is het bestand te groot?',

    // Grenzen. De vier varianten zijn geen keuze van ons: Laravel kiest er
    // zelf een op grond van wat er in het veld staat.
    'max' => [
        'string' => 'Houd :attribute op maximaal :max tekens.',
        'numeric' => 'Kies bij :attribute een waarde van maximaal :max.',
        'file' => 'Houd :attribute onder :max kilobyte.',
        'array' => 'Kies bij :attribute maximaal :max onderdelen.',
    ],

    'min' => [
        'string' => 'Gebruik bij :attribute minstens :min tekens.',
        'numeric' => 'Kies bij :attribute een waarde van minstens :min.',
        'file' => 'Het bestand bij :attribute moet minstens :min kilobyte zijn.',
        'array' => 'Kies bij :attribute minstens :min onderdelen.',
    ],

    // Het huidige wachtwoord. Noemt met opzet geen veldnaam: dit is altijd
    // het wachtwoord en nooit iets anders.
    'current_password' => 'Het wachtwoord is onjuist.',

    /*
     * De eisen aan een nieuw wachtwoord, zoals AppServiceProvider ze in
     * productie zet: minstens twaalf tekens, hoofd- en kleine letters,
     * cijfers, een bijzonder teken, en niet in een bekend datalek.
     *
     * Geen van deze zinnen bevat het wachtwoord zelf, en dat moet zo
     * blijven -- zie regel 3 in AGENTS.md.
     */
    'password' => [
        'letters' => 'Gebruik in :attribute minstens één letter.',
        'mixed' => 'Gebruik in :attribute minstens één hoofdletter en één kleine letter.',
        'numbers' => 'Gebruik in :attribute minstens één cijfer.',
        'symbols' => 'Gebruik in :attribute minstens één bijzonder teken.',
        'uncompromised' => 'Dit wachtwoord staat in een bekend datalek. Kies een ander.',
    ],

    /*
     * Hier staan met opzet géén veldnamen.
     *
     * Elke FormRequest geeft ze zelf mee via `attributes()`, in de woorden
     * die bij dát scherm horen -- `label_nl` is op het ene scherm "naam" en
     * op het andere "titel". Eén centrale lijst zou die nuance platslaan en
     * bij elk nieuw scherm vergeten worden.
     */
    'attributes' => [],

];
