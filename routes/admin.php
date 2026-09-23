<?php

use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\MailLogController;
use App\Http\Controllers\Admin\SecurityEventController;
use App\Http\Controllers\Admin\UserController;
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

Route::middleware(['auth', 'verified'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', [DashboardController::class, 'index'])
            ->middleware('can:view security log')
            ->name('dashboard');

        Route::get('mail', [MailLogController::class, 'index'])
            ->middleware('can:view mail log')
            ->name('mail.index');

        Route::get('security', [SecurityEventController::class, 'index'])
            ->middleware('can:view security log')
            ->name('security.index');

        // Kijken mag met alleen het recht. Wijzigen vraagt daarnaast om een
        // verse authenticator-code: rollen uitdelen en accounts verwijderen
        // zijn precies de handelingen die je niet wilt terugdraaien.
        Route::get('users', [UserController::class, 'index'])
            ->middleware('can:manage users')
            ->name('users.index');

        Route::put('users/{user}/roles', [UserController::class, 'updateRoles'])
            ->middleware(['can:manage users', '2fa.confirm'])
            ->name('users.roles.update');

        Route::delete('users/{user}', [UserController::class, 'destroy'])
            ->middleware(['can:manage users', '2fa.confirm'])
            ->name('users.destroy');
    });
