<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionKey;
use App\Models\AboutPoint;
use App\Models\AboutSetting;
use App\Models\SectionHeading;
use App\Support\Page\Navigatie;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De aparte pagina "Over mij".
 *
 * **Hij bestaat alleen als de eigenaar hem aanzet én er iets in staat.**
 * Dat zijn twee voorwaarden en niet één, en het verschil doet ertoe: zet
 * hij het schuifje om maar vult hij niets in, dan zou er een pagina met
 * alleen een kop komen te staan -- met een knop op zijn voorpagina die
 * daarheen wijst. Een 404 is dan eerlijker dan een lege pagina.
 * `paginaStaatKlaar()` stelt die vraag op één plek, zodat het blok op de
 * voorpagina en deze route niet uiteen kunnen lopen.
 *
 * Staat het hele onderdeel uit op de indelingspagina, dan bestaat deze
 * pagina ook niet: dan wil de eigenaar geen "Over mij", en een adres dat er
 * toch is leidt bezoekers naar iets dat hij heeft weggehaald.
 *
 * Volgt de aparte contactpagina als voorbeeld: een `__invoke`, alle
 * variabele waarden uit de bron die ze bepaalt, en een pagina in
 * `pages/public/` die daarmee vanzelf de publieke layout krijgt.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
class PublicAboutController extends Controller
{
    public function __invoke(Navigatie $navigatie): Response
    {
        $instelling = AboutSetting::huidige();

        abort_unless($instelling->paginaStaatKlaar(), 404);

        /*
         * Staat het onderdeel uit op de indelingspagina, dan is er geen
         * "Over mij" -- ook niet op een eigen adres.
         *
         * Via het model en niet met een eigen query hier, zodat deze route
         * en de voorpagina dezelfde vraag op dezelfde manier stellen.
         */
        abort_unless($instelling->sectieStaatAan(), 404);

        return Inertia::render('public/OverMij', [
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

            /*
             * De kop van de pagina komt uit de instellingen en niet uit
             * `section_headings`: die laatste hoort bij het blok op de
             * voorpagina. Een pagina met dezelfde titel als het blok waar
             * je vandaan klikt leest alsof je niet bent verdergegaan.
             *
             * Vult de eigenaar geen titel in, dan valt hij terug op de
             * titel van het blok -- beter een kop die dubbelt dan een
             * pagina zonder kop.
             */
            'titel' => $instelling->paginaTitel()
                ?? SectionHeading::voor(PageSectionKey::OverMij)->voorDeSite()['titel'],

            'inleiding' => $instelling->paginaInleiding(),
            'verhaal' => $instelling->verhaal(),
            'foto' => $instelling->foto(),

            /*
             * De punten, al gezeefd op taal: een punt zonder Engels
             * verdwijnt voor een Engelse bezoeker in plaats van terug te
             * vallen. Zie AboutPoint.
             */
            'punten' => AboutPoint::query()
                ->opVolgorde()
                ->get()
                ->map(fn (AboutPoint $punt) => $punt->tekst())
                ->filter()
                ->values()
                ->all(),

            'email' => config('site.email'),
        ]);
    }
}
