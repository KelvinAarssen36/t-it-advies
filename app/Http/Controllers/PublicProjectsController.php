<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionKey;
use App\Models\PageSection;
use App\Models\Project;
use App\Models\ProjectSetting;
use App\Models\SectionHeading;
use App\Support\Page\Navigatie;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De pagina met alle projecten.
 *
 * **Hij bestaat alleen als er iets te tonen is.** Het onderdeel moet
 * aanstaan op de indelingspagina én er moet minstens één project online
 * staan. Een adres dat bestaat maar leeg is, is erger dan een adres dat
 * niet bestaat -- en de knop ernaartoe staat alleen op de voorpagina als
 * het onderdeel daar ook staat.
 *
 * Volgt de aparte contactpagina als voorbeeld: een `__invoke`, het menu
 * via `Navigatie`, en een pagina in `pages/public/` die daarmee vanzelf
 * de publieke layout krijgt.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
class PublicProjectsController extends Controller
{
    public function __invoke(Navigatie $navigatie): Response
    {
        abort_unless(self::sectieStaatAan(), 404);

        $projecten = Project::query()->online()->opVolgorde()->get();

        abort_if($projecten->isEmpty(), 404);

        /*
         * De pagina valt in twee stukken: bovenaan de uitgelichte
         * projecten als slideshow over de volle breedte, daaronder de
         * rest in een ritme van breed en smal.
         *
         * **Een project staat in één van de twee, nooit in allebei.**
         * Zou het uitgelichte project ook nog tussen de kaarten staan,
         * dan lijkt het alsof de eigenaar het twee keer heeft ingevoerd.
         * Licht hij niets uit, dan is er geen slideshow en begint de
         * pagina gewoon met de lijst -- dan staat er niets boven dat
         * belooft wat er niet is.
         */
        $uitgelicht = $projecten->where('featured', true)->values();
        $overige = $projecten->where('featured', false)->values();

        return Inertia::render('public/Projecten', [
            'navigation' => $navigatie->menu(),

            /*
             * Dezelfde kop als boven het blok op de voorpagina. Anders
             * dan bij "Over mij" krijgt deze pagina geen eigen titel: het
             * is dezelfde etalage, alleen zonder de grens van vier.
             */
            'kop' => SectionHeading::voor(PageSectionKey::Projecten)->voorDeSite(),

            // Afwisselend groot en klein, of allemaal even grote
            // kaarten. De eigenaar kiest; zie App\Enums\ProjectWeergave.
            'weergave' => ProjectSetting::weergave()->value,

            'featured' => $uitgelicht
                ->map(fn (Project $project) => $project->voorDeKaart())
                ->all(),

            'projects' => $overige
                ->map(fn (Project $project) => $project->voorDeKaart())
                ->all(),
        ]);
    }

    /**
     * Staat het onderdeel aan op de indelingspagina?
     *
     * Zelfde patroon als `AboutSetting::sectieStaatAan()`, inclusief de
     * terugval: zolang `page_sections` leeg is -- een database waarin nog
     * nooit is geseed -- geldt alles als aan. Anders zou de website op
     * een verse installatie leeg zijn zonder dat iets zegt waarom.
     */
    public static function sectieStaatAan(): bool
    {
        $rij = PageSection::query()
            ->where('key', PageSectionKey::Projecten->value)
            ->first();

        return $rij === null || $rij->visible;
    }
}
