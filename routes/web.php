<?php

use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FallbackController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\Portal\SiteEntryController;
use App\Http\Controllers\PrivacyController;
use App\Http\Controllers\PublicAboutController;
use App\Http\Controllers\PublicContactController;
use App\Http\Controllers\Security\ConfirmTwoFactorController;
use App\Http\Controllers\Security\PortalEntryController;
use App\Http\Controllers\Security\TwoFactorSetupController;
use App\Http\Middleware\TelBezoek;
use Illuminate\Support\Facades\Route;
use Spatie\Honeypot\ProtectAgainstSpam;

/*
|--------------------------------------------------------------------------
| Publieke routes
|--------------------------------------------------------------------------
*/

/*
 * De landingspagina. Hier stond `Route::inertia`, maar de pagina haalt nu
 * de volgorde van zijn onderdelen uit de database; zie HomeController.
 *
 * `TelBezoek` staat op deze route en niet globaal: het portaal, de webhooks
 * en /up zijn geen bezoek. Komt er een tweede publieke pagina bij, dan hoort
 * die middleware daar ook op -- en dan is dit het moment om er een groep van
 * te maken.
 */
Route::get('/', HomeController::class)
    ->middleware(TelBezoek::class)
    ->name('home');

/*
 * De privacyverklaring.
 *
 * Een eigen pagina en geen regeltje in de voet, want er staat te veel in om
 * weg te stoppen: wat er wordt verwerkt, waarom, hoe lang en door wie. De
 * tekst staat in de code en niet in de database -- hij beschrijft wat de
 * software doet, dus hij hoort in dezelfde wijziging mee te veranderen als
 * die software. Zie docs/architecture/bezoekcijfers.md.
 */
Route::get('privacy', PrivacyController::class)->name('privacy');

/*
 * De aparte contactpagina.
 *
 * Bestaat alleen als de eigenaar voor die weergave heeft gekozen; anders
 * geeft de controller een 404. Zie PublicContactController.
 *
 * **Mét `TelBezoek`.** Dit is een echte pagina van de website, dus een
 * bezoek hoort mee te tellen -- anders zakken de cijfers zodra hij voor
 * deze weergave kiest. Het commentaar bij de route van de landingspagina
 * zegt dat een tweede publieke pagina deze middleware ook hoort te
 * krijgen; dit is die tweede.
 */
Route::get('contact', PublicContactController::class)
    ->middleware(TelBezoek::class)
    ->name('contact');

/*
 * De aparte pagina "Over mij".
 *
 * Bestaat alleen als de eigenaar hem aanzet én er een verhaal in staat;
 * anders geeft de controller een 404. Die twee voorwaarden samen zijn met
 * opzet: een pagina met alleen een kop, waar een knop op de voorpagina naar
 * wijst, is erger dan geen pagina. Zie PublicAboutController.
 *
 * **Mét `TelBezoek`**, net als de contactpagina: dit is een echte pagina
 * van de website, dus een bezoek hoort mee te tellen.
 */
Route::get('over-mij', PublicAboutController::class)
    ->middleware(TelBezoek::class)
    ->name('over-mij');

/*
 * Van taal wisselen. Open voor iedereen, want de publieke site moet ook
 * vertaalbaar zijn; de controle zit in de witte lijst uit config/app.php.
 */
Route::post('taal/{locale}', LocaleController::class)->name('locale.switch');

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

Route::middleware(['auth', 'verified', 'two-factor.required'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
     * Het korte laadscherm tussen inloggen en het portaal. Staat achter
     * dezelfde middleware als de rest: wie 2FA nog niet heeft ingesteld
     * hoort eerst dáárheen en niet naar een laadscherm.
     */
    Route::get('portaal/binnenkomen', [PortalEntryController::class, 'show'])
        ->name('portal.enter');

    /*
     * En de weg terug: van het portaal naar de website, met hetzelfde
     * laadscherm. Ook achter 'auth', want deze route bestaat alleen voor
     * wie in het portaal zit -- een bezoeker gaat gewoon naar de homepage.
     */
    Route::get('portaal/naar-de-website', SiteEntryController::class)
        ->name('site.enter');
});

/*
 * Het instellen van tweestapsverificatie, verplicht bij het eerste bezoek.
 *
 * Deze twee routes staan bewust NIET achter 'two-factor.required'. Zouden
 * ze dat wel doen, dan stuurt die middleware je naar de pagina waar hij je
 * vervolgens weer vandaan stuurt.
 */
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('security/two-factor/setup', [TwoFactorSetupController::class, 'show'])
        ->name('security.two-factor.setup');

    Route::get('security/two-factor/klaar', [TwoFactorSetupController::class, 'finish'])
        ->name('security.two-factor.setup.finish');
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
require __DIR__.'/website.php';
require __DIR__.'/admin.php';
require __DIR__.'/webhooks.php';

/*
|--------------------------------------------------------------------------
| Alles wat nergens op past
|--------------------------------------------------------------------------
|
| Deze route staat als laatste, want hij vangt af wat geen enkele route
| hierboven heeft opgepakt.
|
| Dat het een route is en geen regel in de foutafhandeling, is de kern van
| de zaak: een 404 op een onbekend adres wordt door de router gegooid
| voordat de middlewaregroep 'web' heeft gedraaid. Er is dan geen sessie,
| dus ook geen ingelogde gebruiker en geen gedeelde Inertia-props. Hier wel.
| Zie FallbackController.
|
*/
Route::fallback(FallbackController::class);
