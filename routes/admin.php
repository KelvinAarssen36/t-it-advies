<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\LegalController;
use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\SecurityEventController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\VisitorController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Beveiligd gedeelte
|--------------------------------------------------------------------------
|
| Alles hier zit achter inloggen, een geverifieerd e-mailadres en een recht
| uit spatie/laravel-permission. De rechten worden geseed door
| RolesAndPermissionsSeeder.
|
| Let op de volgorde: 'auth' en 'verified' staan op de groep, de
| rechtencontrole per route. Zo kun je een rol geven die alleen het
| mailoverzicht mag zien, zonder het beveiligingslogboek.
|
| Voor acties die iets kapot kunnen maken zet je daarnaast de middleware
| '2fa.confirm' op de route. Zie docs/security/gevoelige-acties.md.
|
*/

Route::middleware(['auth', 'verified', 'two-factor.required'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])
            ->middleware('can:manage portal')
            ->name('dashboard');

        /*
         * De bezoekcijfers van de website. Het enige scherm hier dat over
         * de site gaat en niet over het portaal, en het staat er omdat je
         * het nakíjkt -- net als de logboeken ernaast.
         */
        Route::get('bezoekers', [VisitorController::class, 'index'])
            ->middleware('can:manage portal')
            ->name('visitors.index');

        /*
         * Juridisch: een verzoek van een bezoeker afhandelen.
         *
         * Kijken mag met alleen het recht. Het weggooien van mislukte
         * mailpogingen vraagt daarnaast om een verse authenticator-code:
         * daar verdwijnt een bericht van iemand onherstelbaar, en dat is
         * precies de soort handeling waar `2fa.confirm` voor is.
         */
        Route::get('juridisch', [LegalController::class, 'index'])
            ->middleware('can:manage portal')
            ->name('legal.index');

        Route::delete('juridisch/mislukte-mail', [LegalController::class, 'destroyFailedMail'])
            ->middleware(['can:manage portal', '2fa.confirm'])
            ->name('legal.failed-mail.destroy');

        Route::get('activiteit', [ActivityController::class, 'index'])
            ->middleware('can:manage portal')
            ->name('activity.index');

        Route::get('mail', [MailLogController::class, 'index'])
            ->middleware('can:manage portal')
            ->name('mail.index');

        Route::get('security', [SecurityEventController::class, 'index'])
            ->middleware('can:manage portal')
            ->name('security.index');

        // Kijken mag met alleen het recht. Wijzigen vraagt daarnaast om een
        // verse authenticator-code: rollen uitdelen en accounts verwijderen
        // zijn precies de handelingen die je niet wilt terugdraaien.
        Route::get('users', [UserController::class, 'index'])
            ->middleware('can:manage portal')
            ->name('users.index');

        Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])
            ->middleware(['can:manage portal', '2fa.confirm'])
            ->name('users.roles.update');

        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware(['can:manage portal', '2fa.confirm'])
            ->name('users.destroy');
    });
