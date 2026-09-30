<?php

use App\Http\Controllers\Website\ExperienceController;
use App\Http\Controllers\Website\LayoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| De website van de klant
|--------------------------------------------------------------------------
|
| Alles waarmee de eigenaar de inhoud van zijn eigen site bijhoudt. Dit is
| niet hetzelfde als routes/admin.php: dáár staan de logboeken en het
| gebruikersbeheer, dingen die je naslaat. Hier staat wat hij maakt.
|
| Dat het achter hetzelfde recht zit -- 'manage portal' -- is geen
| slordigheid maar de afspraak: er is één recht en één rol, en die heeft
| alles. Zie docs/security/rollen-en-rechten.md.
|
| Zodra de eerste module er is komt die hier onder te staan, met de
| indeling als startpunt erboven.
|
*/

Route::middleware(['auth', 'verified', 'two-factor.required', 'can:manage portal'])
    ->prefix('website')
    ->name('website.')
    ->group(function () {
        Route::get('/', [LayoutController::class, 'index'])->name('index');

        /*
         * Volgorde en zichtbaarheid gaan in één opslag, en dus ook door één
         * bevestiging. Zou elk schuifje meteen iets opslaan, dan krijgt de
         * eigenaar bij elke klik twee vragen over iets dat live gaat -- en
         * dan klikt hij ze weg zonder te lezen.
         */
        Route::put('/', [LayoutController::class, 'update'])->name('update');

        /*
         * De tijdlijn met ervaringen: de eerste module waarmee de klant
         * echte inhoud beheert.
         *
         * Geen `2fa.confirm` op de wijzigende routes, anders dan bij het
         * gebruikersbeheer. Daar kun je jezelf buitensluiten; hier gaat het
         * om inhoud, met een dubbele bevestiging in het scherm en een regel
         * in het activiteitenlogboek waarin staat wát er stond. Zie
         * ExperienceController.
         */
        Route::prefix('ervaring')->name('ervaring.')->group(function () {
            Route::get('/', [ExperienceController::class, 'index'])->name('index');
            Route::post('/', [ExperienceController::class, 'store'])->name('store');

            /*
             * De vertaalknop staat vóór de detailpagina, want `{experience}`
             * vangt anders ook `vertalen` op. Hij is weliswaar een POST en
             * de detailpagina een GET, maar die volgorde is precies het
             * soort detail dat bij de volgende route stilletjes misgaat.
             *
             * Begrensd omdat elke aanroep tekens van het maandtegoed kost:
             * een knop die per ongeluk in een lus staat, kan dat tegoed in
             * een paar minuten opmaken.
             */
            Route::post('vertalen', [ExperienceController::class, 'vertalen'])
                ->middleware('throttle:vertalen')
                ->name('vertalen');

            /*
             * De kop boven de tijdlijn: de tekst én de cijfers. Eén
             * scherm en één opslag, want het is op de website ook één
             * blok.
             *
             * Staat óók vóór `{experience}`, en hier is dat geen voorzorg
             * maar noodzaak: allebei zijn het een PUT, en de eerste die
             * past wint.
             */
            Route::put('kop', [ExperienceController::class, 'kop'])->name('kop');

            Route::get('{experience}', [ExperienceController::class, 'show'])->name('show');
            Route::put('{experience}', [ExperienceController::class, 'update'])->name('update');

            /*
             * Het schuifje online/offline in de lijst. Een eigen route,
             * omdat één waarde omzetten niet het hele formulier langs de
             * validatie hoort te sturen.
             */
            Route::patch('{experience}/online', [ExperienceController::class, 'online'])
                ->name('online');
            Route::delete('{experience}', [ExperienceController::class, 'destroy'])->name('destroy');
        });
    });
