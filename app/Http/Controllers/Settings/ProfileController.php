<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\ProfileUpdateRequest;
use App\Models\EmailChange;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De profielinstellingen.
 *
 * Er zit geen `destroy` op. Dit portaal heeft één account en registratie
 * staat uit, dus je eigen account verwijderen is geen functie maar een
 * manier om jezelf buiten te sluiten. Zie routes/settings.php.
 */
class ProfileController extends Controller
{
    /**
     * Show the user's profile settings page.
     */
    public function edit(Request $request): Response
    {
        /** @var User $gebruiker */
        $gebruiker = $request->user();

        return Inertia::render('settings/Profile', [
            /*
             * Een vaste `true`. Hier stond een instanceof-controle uit de
             * starter kit, maar ons User-model implementeert dat contract
             * gewoon, dus die controle was altijd waar. De prop zelf blijft:
             * het scherm leest hem.
             */
            'mustVerifyEmail' => true,
            'status' => $request->session()->get('status'),

            /*
             * Een aanvraag die nog op bevestiging wacht. Zonder dit zou
             * het scherm doen alsof er niets loopt, terwijl er een
             * bevestigingslink in een ander postvak ligt.
             */
            /*
             * Komt de eigenaar net langs `inlogadres.create` -- en dus
             * langs zijn authenticator -- dan gaat het venster meteen
             * open. Zie EmailChangeController::create().
             */
            'openInlogadresVenster' => $request->session()->get('inlogadresVenster') === true,

            'openstaandeWijziging' => EmailChange::query()
                ->where('user_id', $gebruiker->id)
                ->openstaand()
                ->latest('id')
                ->first()
                ?->voorHetScherm(),
        ]);
    }

    /**
     * De naam bijwerken.
     *
     * **Het e-mailadres loopt hier niet meer langs.** Dat heeft een eigen
     * stroom met drie sloten ervoor en een weg terug erna; zie
     * EmailChangeController. Hier stond het gewoon tussen de velden, en
     * dan is het inlogadres van het portaal één toetsaanslag van een adres
     * dat niet bestaat.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated())->save();

        Toast::bijgewerkt(__('Je profiel is bijgewerkt.'));

        return to_route('profile.edit');
    }
}
