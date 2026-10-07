<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Support\Page\Navigatie;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De pagina van één project.
 *
 * **Een eigen adres en geen venster, en dat is een keuze die uitleg
 * verdient.** Elders op deze site opent "meer over dit item" een overlay
 * zonder route -- zie `ErvaringVenster`. Voor een project is dat niet
 * genoeg, om drie redenen:
 *
 * 1. **Je stuurt het iemand toe.** Een project is precies het soort ding
 *    dat de eigenaar in een mail of op LinkedIn deelt. Een overlay heeft
 *    geen adres, dus dat kan niet.
 * 2. **De sitemap.** `docs/openstaand.md` zegt dat een sitemap pas zinvol
 *    is "zodra er pagina's zijn, en die komen uit de modules". Dit is de
 *    eerste module die er echt meerdere oplevert.
 * 3. **Dit project koos al twee keer voor vindbaarheid**: de vragenlijst
 *    houdt álle vragen in de DOM, ook die van de volgende bladzijde, en
 *    levert structuurdata mee. Diezelfde afweging geldt hier sterker.
 *
 * De prijs is de eerste slug en de eerste publieke route met een
 * parameter in dit project. Dat is één kolom en een unieke index; zie
 * `Project::vrijeSlug()` voor waarom een titelwijziging het adres met
 * rust laat.
 *
 * **Een project dat offline staat bestaat hier niet.** Niet "leeg" en
 * niet "verborgen", maar een 404 -- anders is het adres een achterdeur
 * naar iets wat de eigenaar bewust van zijn site heeft gehaald.
 *
 * **En deze pagina onthoudt waar je vandaan kwam.** Hij is de enige op de
 * site met twee bovenliggende plekken: de voorpagina en `/projecten`. Eén
 * vaste terugknop zou in de helft van de gevallen naar een pagina wijzen
 * waar de bezoeker nooit is geweest; `?van=start` lost dat op.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
class PublicProjectController extends Controller
{
    /**
     * De plekken waar een bezoeker vandaan kan komen.
     *
     * Een korte witte lijst en geen vrije waarde: alles wat er niet in
     * staat valt terug op de lijst. Zonder die lijst zet iemand
     * `?van=<wat dan ook>` in het adres en staat dat in het scherm.
     */
    private const HERKOMST = ['start'];

    public function __invoke(Navigatie $navigatie, Request $request, Project $project): Response
    {
        /*
         * Twee grendels, en allebei nodig. De eerste houdt een offline
         * project tegen; de tweede het geval waarin de eigenaar het hele
         * onderdeel van zijn site heeft gehaald. Zonder die tweede blijft
         * elk project bereikbaar voor wie het adres kent.
         */
        abort_unless($project->published, 404);
        abort_unless(PublicProjectsController::sectieStaatAan(), 404);

        /*
         * Waar de bezoeker vandaan kwam, zodat de knop onderaan hem
         * terugbrengt naar de pagina die hij verliet en niet naar een
         * pagina waar hij nooit is geweest.
         *
         * **Via het adres en niet via de verwijzer van de browser.** De
         * site wisselt van pagina zonder de browser te laten navigeren,
         * dus die verwijzer klopt hier niet. En omdat het in het adres
         * staat werkt het ook na verversen en op de server -- dus de knop
         * staat meteen goed in plaats van na een flikkering.
         *
         * Wie de link deelt, deelt meestal het adres zonder `?van=`, en
         * dan is "Terug naar alle projecten" precies goed.
         */
        $van = (string) $request->query('van', '');

        return Inertia::render('public/ProjectDetail', [
            'navigation' => $navigatie->menu(),
            'project' => $project->voorDePagina(),
            'van' => in_array($van, self::HERKOMST, true) ? $van : null,
        ]);
    }
}
