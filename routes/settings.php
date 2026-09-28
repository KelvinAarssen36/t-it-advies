<?php

use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SecurityController;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Support\Facades\Route;

/*
 * Ook de instellingen zitten achter 'two-factor.required'. Zonder dat zou
 * je het portaal via een omweg toch kunnen gebruiken zonder 2FA in te
 * stellen -- en je profiel wijzigen is ook gewoon het portaal.
 */
Route::middleware(['auth', 'two-factor.required'])->group(function () {
    Route::redirect('settings', '/settings/profile');

    Route::get('settings/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('settings/profile', [ProfileController::class, 'update'])->name('profile.update');
});

/*
 * Er is bewust géén route om je eigen account te verwijderen.
 *
 * Dit portaal heeft één account, en registratie staat uit. Wie dat account
 * weghaalt sluit zichzelf buiten en er is daarna niemand meer die kan
 * inloggen. Een beheerder kan andere accounts wél verwijderen; zie
 * routes/admin.php, met de vangnetten die daar beschreven staan.
 */
Route::middleware(['auth', 'verified', 'two-factor.required'])->group(function () {
    Route::get('settings/security', [SecurityController::class, 'edit'])
        ->middleware(RequirePassword::class)
        ->name('security.edit');

    Route::put('settings/password', [SecurityController::class, 'update'])
        ->middleware('throttle:6,1')
        ->name('user-password.update');

    Route::inertia('settings/appearance', 'settings/Appearance')->name('appearance.edit');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
