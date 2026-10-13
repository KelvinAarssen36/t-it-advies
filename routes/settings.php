<?php

use App\Http\Controllers\Settings\DashboardSettingsController;
use App\Http\Controllers\Settings\EmailChangeController;
use App\Http\Controllers\Settings\MailStijlController;
use App\Http\Controllers\Settings\MailVoorbeeldController;
use App\Http\Controllers\Settings\PasskeyStepController;
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

    /*
     * Het inlogadres wijzigen staat los van het profiel, met drie sloten:
     * een verse authenticator-code (`2fa.confirm`), het huidige wachtwoord
     * (in EmailChangeRequest) en een bevestiging vanuit het nieuwe postvak.
     *
     * De begrenzing is krap met opzet. Elke aanvraag stuurt twee mails, en
     * een knop die per ongeluk in een lus komt, maakt van het oude postvak
     * een spamdoel -- precies het postvak dat je wil kunnen vertrouwen.
     */
    /*
     * De deur naar het venster, en meteen het eerste slot. Deze route doet
     * zelf niets -- hij stuurt meteen terug naar het profiel -- maar hij
     * staat achter `2fa.confirm`. Daardoor wordt de authenticator gevraagd
     * *voordat* de eigenaar iets intypt.
     *
     * Dat is niet alleen vriendelijker. Zonder deze stap vraagt de
     * middleware de code pas bij het versturen; de eigenaar komt dan na het
     * invoeren van zijn code terug op een leeg scherm en moet het hele
     * formulier opnieuw invullen. Precies het moment waarop iemand denkt
     * dat er iets stuk is.
     */
    Route::get('settings/inlogadres', [EmailChangeController::class, 'create'])
        ->middleware('2fa.confirm')
        ->name('inlogadres.create');

    Route::post('settings/inlogadres', [EmailChangeController::class, 'store'])
        ->middleware(['2fa.confirm', 'throttle:inlogadres'])
        ->name('inlogadres.store');
});

/*
 * De twee links uit de mails. **Zonder inloggen**, en dat is met opzet.
 *
 * `bevestigen` komt in het nieuwe postvak terecht en bewijst daarmee wat
 * hij moet bewijzen: dat dat postvak bestaat. Wie de aanvrager is, is al
 * bewezen met wachtwoord én code.
 *
 * `terugdraaien` is het vangnet voor precies de situatie waarin je niet
 * meer binnenkomt. Een herstellink achter een inlogscherm is geen
 * herstellink.
 *
 * Allebei met een eenmalig token dat gehasht in de database staat; zie
 * App\Models\EmailChange. De begrenzing voorkomt dat iemand tokens staat
 * te raden.
 */
Route::middleware('throttle:inlogadres-link')->group(function () {
    Route::get('inlogadres/bevestigen/{token}', [EmailChangeController::class, 'confirm'])
        ->name('inlogadres.bevestigen');

    Route::get('inlogadres/terugdraaien/{token}', [EmailChangeController::class, 'revert'])
        ->name('inlogadres.terugdraaien');
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
     * De extra stap na een passkey, aan en uit.
     *
     * **Niet achter `2fa.confirm`**, hoewel het wel degelijk een gevoelige
     * actie is. Die middleware kan een PUT niet onthouden: hij stuurt je
     * naar het codescherm en gooit het verzoek weg, zodat het schuifje
     * terugspringt en je het nog een keer moet omzetten.
     *
     * De code zit daarom in het verzoek zelf, gecontroleerd door dezelfde
     * klasse die dat codescherm gebruikt. Dezelfde begrenzer ook, want het
     * is dezelfde soort poging. Zie PasskeyStepController en
     * App\Support\Security\Authenticator.
     */
    Route::put('settings/security/passkey-stap', [PasskeyStepController::class, 'update'])
        ->middleware('throttle:sensitive-action')
        ->name('security.passkey-stap');

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
