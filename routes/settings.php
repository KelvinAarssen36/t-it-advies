<?php

use App\Http\Controllers\Settings\DashboardSettingsController;
use App\Http\Controllers\Settings\MailStijlController;
use App\Http\Controllers\Settings\MailVoorbeeldController;
use App\Http\Controllers\Settings\ProfileController;
use App\Http\Controllers\Settings\SafetyController;
use App\Http\Controllers\Settings\SecurityController;
use App\Http\Controllers\Settings\WeergaveController;
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

    /*
     * Weergave. Sinds de mailstijl een instelling is kan dit geen
     * `Route::inertia` meer zijn: het scherm moet weten welke stijl er nu
     * staat, en dat komt uit de database.
     */
    Route::get('settings/appearance', WeergaveController::class)->name('appearance.edit');

    Route::put('settings/appearance/mail', [MailStijlController::class, 'update'])
        ->name('appearance.mail-stijl');

    /*
     * Het voorbeeld van de mailstijl, als kale HTML voor een `iframe` op
     * dat scherm. Geen Inertia: een mail heeft zijn opmaak in de tags
     * zelf, en die zou met het portaal vechten. Zie
     * MailVoorbeeldController.
     */
    Route::get('settings/appearance/mail', [MailVoorbeeldController::class, 'bevestiging'])
        ->name('appearance.mail');

    /*
     * En de bouwstenen die in de ándere mails voorkomen: een knop, een
     * uitgelicht vak, een tabel. Een eigen adres en geen schakelaar op het
     * eerste voorbeeld, want het zijn twee `iframe`s naast elkaar op het
     * scherm Weergave.
     */
    Route::get('settings/appearance/mail/onderdelen', [MailVoorbeeldController::class, 'onderdelen'])
        ->name('appearance.mail-onderdelen');

    /*
     * De instellingen van het dashboard: nu de tijdzone van de klok, later
     * meer. Een eigen gedeelte en geen regel bij "Weergave", want dat gaat
     * over licht en donker in het hele portaal; dit gaat over één scherm.
     */
    Route::get('settings/dashboard', [DashboardSettingsController::class, 'edit'])
        ->name('dashboard-settings.edit');

    /*
     * Hoe het allemaal beveiligd is. Een scherm zonder knoppen: het legt
     * uit wat er onder water gebeurt en laat een paar echte cijfers zien.
     *
     * Het staat níet achter een wachtwoordbevestiging zoals het scherm
     * Beveiliging, en dat is geen slordigheid: daar wijzig je iets dat je
     * niet wilt terugdraaien, hier lees je alleen. Een extra drempel voor
     * lezen zou betekenen dat hij het nooit opent.
     */
    Route::get('settings/veiligheid', [SafetyController::class, 'edit'])
        ->name('safety.show');

    Route::patch('settings/dashboard', [DashboardSettingsController::class, 'update'])
        ->name('dashboard-settings.update');

    /*
     * De handleiding voor de eigenaar. Een gewone Inertia-pagina zonder
     * controller: alles wat erop staat beschrijft hoe het portaal werkt en
     * komt dus uit de code, niet uit de database.
     *
     * Hij staat bewust in de instellingen en niet in het menu van de
     * website: je zoekt hem op als je iets niet weet, en niet elke dag.
     * Zie docs/architecture/uitleg-voor-de-eigenaar.md.
     */
    Route::inertia('settings/documentation', 'settings/Documentatie')->name('documentation.show');
});

Route::get('.well-known/passkey-endpoints', function () {
    return response()->json([
        'enroll' => route('security.edit'),
        'manage' => route('security.edit'),
    ]);
})->name('well-known.passkeys');
