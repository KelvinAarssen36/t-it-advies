<?php

use App\Http\Controllers\Website\CertificateController;
use App\Http\Controllers\Website\ExperienceController;
use App\Http\Controllers\Website\HeroController;
use App\Http\Controllers\Website\LayoutController;
use App\Http\Controllers\Website\ServiceController;
use App\Http\Controllers\Website\StatisticController;
use App\Http\Controllers\Website\TranslateController;
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
         * De hele indeling in één opslag: de volgorde én alle schuifjes,
         * door één bevestiging. Dat is wat het bewerkvenster stuurt, waar
         * je meerdere dingen tegelijk omzet.
         */
        Route::put('/', [LayoutController::class, 'update'])->name('update');

        /*
         * Eén onderdeel aan of uit, rechtstreeks vanaf het overzicht.
         *
         * **Dit stond er eerst niet, en dat was een fout die de eigenaar
         * meldde.** Het schuifje stond op het overzicht wél te zien maar
         * uitgeschakeld, zonder uitleg waarom: "het is wel raar dat dat bij
         * allemaal zo is dat ik ze niet uit of aan kan zetten". Er was een
         * reden -- alles ging via het bewerkvenster, in één opslag -- maar
         * die was nergens te lezen, en bovendien werkt élke andere lijst in
         * het portaal wel zo: één schuifje, één bevestiging. Nu hier ook.
         */
        Route::patch('zichtbaar/{sectie}', [LayoutController::class, 'zichtbaar'])
            ->name('zichtbaar');

        /*
         * De vertaalknop, voor élk beheerscherm van de website en niet
         * voor één module.
         *
         * Hij stond onder `ervaring`, en dat hield op te kloppen zodra de
         * kop van de landingspagina hem ook nodig had: die zou dan naar
         * `website/ervaring/vertalen` moeten posten. Eén adres met een
         * veld per soort tekst; zie TranslateController.
         *
         * Begrensd omdat elke aanroep tekens van het maandtegoed kost:
         * een knop die per ongeluk in een lus staat, kan dat tegoed in een
         * paar minuten opmaken.
         */
        Route::post('vertalen', TranslateController::class)
            ->middleware('throttle:vertalen')
            ->name('vertalen');

        /*
         * De kop van de landingspagina: het opschrift, de titel en de zin
         * eronder. Drie teksten, meer niet -- vandaar één scherm met één
         * bewerkvenster en geen lijst met een detailpagina.
         */
        Route::prefix('kop')->name('kop.')->group(function () {
            Route::get('/', [HeroController::class, 'index'])->name('index');
            Route::put('/', [HeroController::class, 'update'])->name('update');
        });

        /*
         * De diensten. Een lijst zoals bij de ervaring, maar zonder
         * detailpagina en met een volgorde die de klant zelf bepaalt.
         *
         * `kop` en `volgorde` staan vóór `{service}`, en dat is geen
         * voorzorg maar noodzaak: het zijn allebei een PUT op hetzelfde
         * patroon, en de eerste die past wint. Staat de volgorde ooit
         * andersom, dan komt een verzoek voor de kop bij `update()`
         * terecht en faalt het op een ontbrekende titel.
         */
        Route::prefix('diensten')->name('diensten.')->group(function () {
            Route::get('/', [ServiceController::class, 'index'])->name('index');
            Route::post('/', [ServiceController::class, 'store'])->name('store');

            Route::put('kop', [ServiceController::class, 'kop'])->name('kop');
            Route::put('volgorde', [ServiceController::class, 'volgorde'])->name('volgorde');

            Route::put('{service}', [ServiceController::class, 'update'])->name('update');
            Route::patch('{service}/online', [ServiceController::class, 'online'])->name('online');
            Route::delete('{service}', [ServiceController::class, 'destroy'])->name('destroy');
        });

        /*
         * De certificaten, met de opleidingen eronder. Twee lijsten op
         * één scherm, want op de website zijn ze samen één blok.
         *
         * `kop`, `volgorde` en `opleidingen` staan vóór `{certificate}`,
         * en dat is geen voorzorg maar noodzaak: het zijn allemaal een
         * PUT of POST op hetzelfde patroon, en de eerste die past wint.
         * Staat de volgorde ooit andersom, dan komt een verzoek voor de
         * kop bij `update()` terecht en faalt het op een ontbrekende
         * naam.
         */
        Route::prefix('certificaten')->name('certificaten.')->group(function () {
            Route::get('/', [CertificateController::class, 'index'])->name('index');
            Route::post('/', [CertificateController::class, 'store'])->name('store');

            Route::put('kop', [CertificateController::class, 'kop'])->name('kop');
            Route::put('volgorde', [CertificateController::class, 'volgorde'])->name('volgorde');

            Route::prefix('opleidingen')->name('opleidingen.')->group(function () {
                Route::post('/', [CertificateController::class, 'opleidingStore'])->name('store');
                Route::put('{education}', [CertificateController::class, 'opleidingUpdate'])->name('update');
                Route::patch('{education}/online', [CertificateController::class, 'opleidingOnline'])->name('online');
                Route::delete('{education}', [CertificateController::class, 'opleidingDestroy'])->name('destroy');
            });

            Route::put('{certificate}', [CertificateController::class, 'update'])->name('update');
            Route::patch('{certificate}/online', [CertificateController::class, 'online'])->name('online');
            Route::delete('{certificate}', [CertificateController::class, 'destroy'])->name('destroy');
        });

        /*
         * De statistieken: vaardigheden en kengetallen. Eén lijst, geen
         * bijlagen -- qua opzet de eenvoudigste module, met het meeste
         * werk aan de kant van de bezoeker.
         *
         * `kop` en `volgorde` staan ook hier vóór `{statistic}`, want
         * het zijn allemaal een PUT op hetzelfde patroon en de eerste
         * die past wint.
         */
        Route::prefix('statistieken')->name('statistieken.')->group(function () {
            Route::get('/', [StatisticController::class, 'index'])->name('index');
            Route::post('/', [StatisticController::class, 'store'])->name('store');

            Route::put('kop', [StatisticController::class, 'kop'])->name('kop');
            Route::put('volgorde', [StatisticController::class, 'volgorde'])->name('volgorde');

            Route::put('{statistic}', [StatisticController::class, 'update'])->name('update');
            Route::patch('{statistic}/online', [StatisticController::class, 'online'])->name('online');
            Route::delete('{statistic}', [StatisticController::class, 'destroy'])->name('destroy');
        });

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
