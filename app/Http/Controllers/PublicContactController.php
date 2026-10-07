<?php

namespace App\Http\Controllers;

use App\Enums\ContactWeergave;
use App\Enums\PageSectionKey;
use App\Models\ContactSetting;
use App\Models\SectionHeading;
use App\Support\Contact\Contactformulier;
use App\Support\Page\Navigatie;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De aparte contactpagina.
 *
 * **Hij bestaat alleen als de eigenaar daarvoor heeft gekozen.** Staat de
 * weergave op "het formulier onderaan je website", dan is er geen tweede
 * plek met hetzelfde formulier -- dan is dit adres een 404 en geen lege
 * pagina. Twee keer hetzelfde formulier zou ook twee Turnstile-widgets
 * betekenen.
 *
 * Staat het hele onderdeel uit, dan bestaat deze pagina ook niet: dan
 * wil de eigenaar geen contactformulier, en een adres dat er toch is
 * leidt bezoekers naar iets dat hij heeft weggehaald.
 *
 * Volgt `/privacy` als voorbeeld: een `__invoke`, alle variabele waarden
 * uit de bron die ze bepaalt, en een pagina in `pages/public/` die daarmee
 * vanzelf de publieke layout krijgt.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class PublicContactController extends Controller
{
    public function __invoke(Contactformulier $formulier, Navigatie $navigatie): Response
    {
        $instellingen = ContactSetting::huidige();

        if ($instellingen->display !== ContactWeergave::EigenPagina) {
            abort(404);
        }

        /*
         * Staat het onderdeel uit op de indelingspagina, dan is er geen
         * contactformulier -- ook niet op een eigen adres.
         *
         * Via `staatAan()` en niet met een eigen query, want
         * `ContactController` stelt dezelfde vraag voordat hij een
         * inzending aanneemt. Twee eigen queries zijn twee antwoorden die
         * uiteen kunnen gaan lopen.
         */
        abort_unless($formulier->staatAan(), 404);

        return Inertia::render('public/Contact', [
            /*
             * Het menu van de voorpagina.
             *
             * **Hier stond niets, en dat was de oorzaak van de dubbele
             * terugknoppen.** Zonder deze lijst verving de kop de hele
             * navigatiebalk door één "Terug naar de website", en zette
             * deze pagina er ook nog eens zelf een teruglink bij. Met het
             * menu erin is de navigatie zelf de weg terug -- en dan kom je
             * in één klik bij het onderdeel dat je wilde.
             */
            'navigation' => $navigatie->menu(),

            'kop' => SectionHeading::voor(PageSectionKey::Contact)->voorDeSite(),
            'velden' => $formulier->voorDeSite(),
            'onderwerpen' => $formulier->onderwerpenVoorDeSite(),
            'eigenOnderwerpToegestaan' => $formulier->eigenOnderwerpToegestaan(),
            'instellingen' => $formulier->versie(),
            'email' => config('site.email'),
        ]);
    }
}
