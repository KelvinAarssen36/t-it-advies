<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Support\Bezoek\Bezoekcijfers;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het dashboard van het portaal.
 *
 * De begroeting na het inloggen zat hier eerst, maar die hoort bij elke
 * portaalpagina en niet alleen bij deze: wie met een onthouden bestemming
 * binnenkomt landt ergens anders en werd dan niet begroet. Hij wordt nu
 * gedeeld vanuit HandleInertiaRequests.
 */
class DashboardController extends Controller
{
    public function index(Request $request, Bezoekcijfers $cijfers): Response
    {
        /** @var User $gebruiker */
        $gebruiker = $request->user();

        return Inertia::render('Dashboard', [
            /*
             * Het blok linksboven: wat de website in totaal heeft gedaan,
             * en wat er vandaag gebeurt. Het grote getal loopt door zolang
             * de site bestaat; de dagtotalen worden nergens opgeruimd.
             */
            'bezoek' => $cijfers->samenvatting(),

            /*
             * De zone van de klok, als IANA-naam. De browser maakt de tijd
             * en de datum ermee op; zie DigitaleKlok.vue.
             *
             * Hij komt uit `dashboardTijdzone()` en niet rechtstreeks uit
             * de kolom: die is leeg zolang de eigenaar niets heeft gekozen,
             * en de standaard hoort op één plek te staan.
             */
            'tijdzone' => $gebruiker->dashboardTijdzone()->value,
        ]);
    }
}
