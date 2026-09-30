<?php

namespace App\Http\Controllers\Website;

use App\Enums\PageSectionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De kop van de landingspagina: drie teksten, en meer niet.
 *
 * **Het kleinste beheerscherm van de website, en dat is de bedoeling.**
 * Er is één kop, dus er valt niets aan te maken en niets te verwijderen.
 * Geen lijst, geen detailpagina, geen zoekveld -- één overzicht met wat
 * er nu staat, en één knop die het bewerkvenster opent.
 *
 * Toch gelden dezelfde afspraken als bij de tijdlijn, en met opzet:
 *
 * - **Bewerken gebeurt in een venster**, niet op de pagina zelf. Alles
 *   wat de klant hier aanpast staat direct live, dus het verschil tussen
 *   "ik kijk" en "ik wijzig" hoort zichtbaar te zijn.
 * - **Twee bevestigingen**, want dit is een bestaand item wijzigen.
 * - **Allebei de talen los**, met de vertaalknop ernaast. Zie
 *   TranslateController.
 * - **Het activiteitenlogboek** loopt mee via SectionHeading; dit is de
 *   eerste tekst die een bezoeker leest.
 *
 * Zie docs/architecture/modules/kop.md.
 */
class HeroController extends Controller
{
    use BewaartKoptekst;

    public function index(Vertaler $vertaler): Response
    {
        return Inertia::render('website/Kop', [
            /*
             * Allebei de talen los, want dit scherm bewerkt ze allebei.
             * De publieke site krijgt een andere vorm: daar is de keuze
             * tussen Nederlands en Engels al gemaakt. Zie HomeController.
             */
            'kop' => $this->koptekst()->voorHetScherm(),

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    /**
     * De drie teksten opslaan.
     *
     * Eén opslag voor alle drie, want op de website is het één blok. Ze
     * los kunnen bewerken zou betekenen dat de eigenaar drie keer
     * bevestigt voor één zichtbare verandering.
     */
    public function update(Request $request): RedirectResponse
    {
        /*
         * Niets veranderd is geen opslag waard. Zonder deze controle komt
         * er een regel in het activiteitenlogboek en een melding "het is
         * aangepast" terwijl er niets anders is dan daarvoor.
         */
        if (! $this->verwerkKoptekst($request)) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De kop van je landingspagina is aangepast.'));

        return back();
    }

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::Hero;
    }
}
