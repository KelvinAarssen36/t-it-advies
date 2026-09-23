<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\Security\ConfirmTwoFactorController;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

/*
|--------------------------------------------------------------------------
| Publieke routes
|--------------------------------------------------------------------------
*/

Route::inertia('/', 'Welcome')->name('home');

/*
 * Het contactformulier heeft drie lagen bescherming, in deze volgorde:
 * rate limiting (goedkoopst), honeypot (geen netwerkverkeer) en pas daarna
 * Turnstile in de validatie (kost een call naar Cloudflare).
 */
Route::post('contact', [ContactController::class, 'store'])
    ->middleware(['throttle:contact', ProtectAgainstSpam::class])
    ->name('contact.store');

/*
|--------------------------------------------------------------------------
| Ingelogd
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});

/*
 * Bevestiging van een gevoelige actie met een verse authenticator-code.
 * Bewust niet achter 'verified': wie hier belandt is al ingelogd en wordt
 * door de middleware naartoe gestuurd.
 */
Route::middleware('auth')->group(function () {
    Route::get('security/confirm-two-factor', [ConfirmTwoFactorController::class, 'show'])
        ->name('security.two-factor.confirm');

    Route::post('security/confirm-two-factor', [ConfirmTwoFactorController::class, 'store'])
        ->middleware('throttle:sensitive-action')
        ->name('security.two-factor.confirm.store');
});

require __DIR__.'/settings.php';
require __DIR__.'/admin.php';
require __DIR__.'/webhooks.php';
