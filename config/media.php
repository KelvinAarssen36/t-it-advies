<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Beeld dat de klant uploadt
    |--------------------------------------------------------------------------
    |
    | Eén plek voor de grenzen aan alles wat de klant aan afbeeldingen naar
    | binnen stuurt. Ze stonden eerder als losse getallen in de FormRequest,
    | en dat is precies de soort waarde die je op drie plekken tegelijk moet
    | bijstellen zodra hij verandert -- ook in de PHP-instellingen van de
    | server, en daar gaat het dan mis.
    |
    | Zie docs/operations/deployment.md voor wat de hostingomgeving moet
    | toestaan, en docs/architecture/formulieren-en-schuifbalken.md voor wat
    | de browser er vooraf al mee doet.
    |
    */

    'logo' => [

        /*
         * De grootste upload die we accepteren, in kilobytes.
         *
         * **Anderhalve megabyte, en dat is voor een logo al ruim.** Wat er
         * uiteindelijk bewaard wordt is een vierkantje van 256 bij 256;
         * daar blijft in de praktijk tien tot dertig kilobyte van over. De
         * browser verkleint een te groot bestand bovendien al vóór het
         * versturen (zie resources/js/lib/beeldmerk.ts), dus wat hier
         * binnenkomt is normaal gesproken een paar honderd kilobyte. Zelfs
         * een PNG van 1600 bij 1600 met veel kleur blijft hieronder.
         *
         * Deze grens is er dus niet voor de gewone gang van zaken maar als
         * vangnet: voor een browser waarin dat verkleinen niet lukt, en voor
         * iemand die het formulier omzeilt.
         *
         * **Waarom niet gewoon twee megabyte, of vijf?** Omdat PHP zijn
         * eigen grens heeft, en die staat op gedeelde hosting vaak op 2M.
         * Is de onze even hoog of hoger, dan kapt PHP het verzoek af
         * vóórdat Laravel het ziet: geen bestand, geen foutmelding, alleen
         * een formulier dat niets doet. Anderhalve megabyte past onder die
         * standaard én laat ruimte over voor de rest van het formulier.
         *
         * Zet je hem hoger, verhoog dan óók `upload_max_filesize` en
         * `post_max_size` op de server; zie docs/operations/deployment.md.
         * LogoLimietenTest valt om als die twee elkaar in de weg zitten.
         */
        'max_kb' => (int) env('MEDIA_LOGO_MAX_KB', 1536),

        /*
         * De kortste zijde die nog bruikbaar is. Kleiner dan dit wordt op
         * de website een vlek, en verkleinen helpt daar niet tegen.
         */
        'min_zijde' => (int) env('MEDIA_LOGO_MIN_ZIJDE', 48),

        /*
         * De langste zijde die we accepteren.
         *
         * Dit gaat over werkgeheugen, niet over schijfruimte. GD zet een
         * afbeelding uitgepakt in het geheugen: vier bytes per beeldpunt.
         * Bij 3000 bij 3000 is dat 36 MB voor het bronbeeld, en dat past
         * binnen een `memory_limit` van 256M met ruimte over. Bij 5000 bij
         * 5000 -- de vorige waarde -- was het al 100 MB, en dat is op
         * gedeelde hosting een manier om jezelf om te duwen.
         *
         * De browser verkleint alles boven de 1600 pixels al voor het
         * versturen, dus in de praktijk raakt niemand deze grens.
         */
        'max_zijde' => (int) env('MEDIA_LOGO_MAX_ZIJDE', 3000),

    ],

    /*
    |--------------------------------------------------------------------------
    | De foto bij "Over mij"
    |--------------------------------------------------------------------------
    |
    | Een eigen blok en niet de grenzen van een logo hergebruikt, want het is
    | een ander soort beeld. Twee verschillen die ertoe doen:
    |
    | **De kortste zijde ligt veel hoger.** Een logo van 48 pixels is nog
    | bruikbaar in een belletje op de tijdlijn; een portret van 48 pixels op
    | een pagina is een vlek. Tweehonderdveertig is de ondergrens waarmee de
    | uitsnede van 640 nog scherp kan worden.
    |
    | **Er mag meer bestand in.** Een foto van een mens heeft veel meer
    | kleurverloop dan een logo, dus dezelfde pixelmaat levert een groter
    | bestand op. Twee megabyte zou mooi zijn, maar PHP staat op gedeelde
    | hosting vaak op precies 2M -- en dan kapt PHP het verzoek af vóórdat
    | Laravel het ziet: geen bestand, geen foutmelding, een formulier dat
    | niets doet. Vandaar dezelfde veilige anderhalve megabyte als bij een
    | logo. De browser verkleint een te groot bestand bovendien al vóór het
    | versturen; zie resources/js/lib/beeldmerk.ts.
    |
    | De uitsnede zelf is 640 bij 640 en staat als constante op het model,
    | naast het medaillon dat dezelfde maten heeft; zie
    | App\Models\AboutSetting::FOTO_MAAT.
    |
    */

    'portret' => [

        'max_kb' => (int) env('MEDIA_PORTRET_MAX_KB', 1536),

        'min_zijde' => (int) env('MEDIA_PORTRET_MIN_ZIJDE', 240),

        /*
         * Dezelfde reden als bij een logo: dit gaat over werkgeheugen. GD
         * zet een beeld uitgepakt in het geheugen, vier bytes per beeldpunt,
         * dus 3000 bij 3000 is 36 MB.
         */
        'max_zijde' => (int) env('MEDIA_PORTRET_MAX_ZIJDE', 3000),

    ],

];
