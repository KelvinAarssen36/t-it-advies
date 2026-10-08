<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionKey;
use App\Models\PageSection;
use App\Models\SectionHeading;
use App\Models\WorkStep;
use App\Support\Page\Navigatie;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De pagina met de hele werkwijze.
 *
 * **Hij bestaat alleen als er iets te lezen valt.** Het onderdeel moet
 * aanstaan, er moet minstens één stap online staan, én minstens één van
 * die stappen moet een verhaal hebben. Dat laatste is de regel die hem
 * onderscheidt van `/projecten`: daar is elk item op zichzelf de moeite,
 * hier zijn de kaarten op de voorpagina het hele verhaal zolang niemand
 * er iets bij heeft geschreven. Een pagina die precies dezelfde vier
 * zinnen herhaalt is een omweg.
 *
 * Om dezelfde reden verschijnt de knop ernaartoe pas als die pagina echt
 * bestaat; zie `WerkwijzeSection.vue`.
 *
 * Volgt `/projecten` als voorbeeld: een `__invoke`, het menu via
 * `Navigatie`, en een pagina in `pages/public/` die daarmee vanzelf de
 * publieke layout krijgt.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */
class PublicWerkwijzeController extends Controller
{
    public function __invoke(Navigatie $navigatie): Response
    {
        abort_unless(self::sectieStaatAan(), 404);

        $stappen = WorkStep::query()->online()->opVolgorde()->get();

        abort_if($stappen->isEmpty(), 404);
        abort_unless(self::ietsTeLezen($stappen->all()), 404);

        return Inertia::render('public/Werkwijze', [
            'navigation' => $navigatie->menu(),

            // Dezelfde kop als boven het blok op de voorpagina: het is
            // hetzelfde onderdeel, alleen uitgeschreven.
            'kop' => SectionHeading::voor(PageSectionKey::Werkwijze)->voorDeSite(),

            'stappen' => $stappen
                ->map(fn (WorkStep $stap) => $stap->voorDeSite())
                ->all(),
        ]);
    }

    /**
     * Heeft minstens één stap een verhaal in de taal van de bezoeker?
     *
     * In de taal van de bezoeker en niet "in het Nederlands": schreef de
     * eigenaar zijn verhalen alleen in het Nederlands, dan heeft een
     * Engelse bezoeker daar niets aan en hoort die pagina voor hém niet
     * te bestaan.
     *
     * @param  array<int, WorkStep>  $stappen
     */
    public static function ietsTeLezen(array $stappen): bool
    {
        foreach ($stappen as $stap) {
            if ($stap->verhaal() !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * Staat het onderdeel aan op de indelingspagina?
     *
     * Zelfde patroon als `PublicProjectsController::sectieStaatAan()`,
     * inclusief de terugval: zolang `page_sections` leeg is -- een
     * database waarin nog nooit is geseed -- geldt alles als aan. Anders
     * zou de website op een verse installatie leeg zijn zonder dat iets
     * zegt waarom.
     */
    public static function sectieStaatAan(): bool
    {
        $rij = PageSection::query()
            ->where('key', PageSectionKey::Werkwijze->value)
            ->first();

        return $rij === null || $rij->visible;
    }
}
