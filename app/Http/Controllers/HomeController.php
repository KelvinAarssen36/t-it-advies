<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionKey;
use App\Models\Experience;
use App\Models\PageSection;
use App\Support\Loopbaan;
use App\Support\Page\SectionContent;
use Illuminate\Database\Eloquent\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De landingspagina.
 *
 * Hier stond `Route::inertia('/', 'Welcome')` -- de pagina had toen niets
 * van de server nodig. Nu wel: de klant bepaalt zelf in welke volgorde de
 * onderdelen staan en welke hij aan heeft.
 *
 * De kop en de voettekst zitten hier niet bij. Die staan vast bovenaan en
 * onderaan, en de voettekst hoort bovendien bij de layout en niet bij deze
 * pagina. Wat hier uitkomt is dus het middenstuk, op volgorde.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class HomeController extends Controller
{
    public function __invoke(SectionContent $inhoud, Loopbaan $loopbaanCijfers): Response
    {
        $secties = $this->secties($inhoud);

        /*
         * De inhoud van een onderdeel gaat alleen mee als dat onderdeel er
         * ook staat. Anders doet elke bezoeker een query voor een lijst die
         * nergens wordt getoond -- en dat wordt erger met elke module die
         * erbij komt.
         */
        $loopbaan = in_array(PageSectionKey::Ervaring->value, $secties, true)
            ? Experience::query()->online()->opTijdlijn()->get()
            : new Collection;

        return Inertia::render('Welcome', [
            'sections' => $secties,

            'experiences' => $loopbaan
                ->map(fn (Experience $ervaring) => $this->ervaring($ervaring))
                ->all(),

            /*
             * De drie cijfers boven de tijdlijn. Ze worden berekend uit
             * dezelfde opgehaalde rijen, tenzij de klant er zelf een getal
             * heeft neergezet. Die afweging staat in App\Support\Loopbaan,
             * want het beheerscherm moet hem ook kennen.
             */
            'experienceSummary' => $loopbaanCijfers->cijfers($loopbaan),
        ]);
    }

    /**
     * Eén ervaring, in de taal van de bezoeker.
     *
     * Alles is hier al beslist: welk veld terugvalt op het Nederlands, wat
     * er wordt weggelaten en hoe de periode eruitziet. De Vue-component
     * toont alleen nog wat er is. Zie App\Models\Experience.
     *
     * @return array<string, mixed>
     */
    private function ervaring(Experience $ervaring): array
    {
        return [
            'id' => $ervaring->id,
            'icon' => $ervaring->icon->value,
            'functie' => $ervaring->functie(),
            'organisatie' => $ervaring->organisation,
            'website' => $ervaring->website(),

            // Het logo van de organisatie, als de klant er een uploadde.
            // Staat er geen, dan valt de tijdlijn terug op het pictogram.
            'logo' => $ervaring->logo(),

            /*
             * De losse delen, zonder de lege ertussen. De puntjes zet de
             * CSS erbij; zou je ze hier in de tekst plakken, dan blijft er
             * eentje staan zodra een deel ontbreekt.
             */
            'meta' => array_values(array_filter([
                $ervaring->plaats(),
                $ervaring->workplace?->label(),
                $ervaring->employment?->label(),
            ])),

            'periode' => $ervaring->periode(),
            'duur' => $ervaring->duur(),
            // Alleen om de tijdlijn in jaargroepen te zetten; op de kaart
            // zelf staat de volledige periode.
            'jaar' => $ervaring->started_on->format('Y'),
            'loopt' => $ervaring->loopt(),
            'beschrijving' => $ervaring->beschrijving(),
        ];
    }

    /**
     * De sleutels van de onderdelen die daadwerkelijk op de pagina komen.
     *
     * Er zijn twee redenen waarom een onderdeel hier níet in staat, en het
     * verschil doet ertoe op het indelingsscherm:
     *
     * 1. De klant heeft hem **uitgezet**. Een keuze, dus geen waarschuwing.
     * 2. Er staat **niets in**. Geen keuze maar iets dat nog moet gebeuren,
     *    en dat krijgt daar een uitroepteken.
     *
     * Voor de bezoeker maakt het niets uit: in allebei de gevallen is het
     * onderdeel er niet, want een kopje met niets eronder is slordiger dan
     * geen kopje.
     *
     * @return array<int, string>
     */
    private function secties(SectionContent $inhoud): array
    {
        $rijen = PageSection::query()->aangezet()->opVolgorde()->get();

        /*
         * Is er nog nooit geseed, dan valt de pagina terug op de volgorde
         * uit de code. Anders levert een vergeten `db:seed` een website op
         * die alleen nog uit een kop en een voettekst bestaat -- en dat is
         * precies het soort fout dat je op de publieke site niet wilt laten
         * afhangen van of iemand eraan gedacht heeft.
         */
        $sleutels = $rijen->isEmpty()
            ? PageSectionKey::verplaatsbaar()
            : $rijen->map(fn (PageSection $rij) => $rij->key)->all();

        return array_values(array_map(
            fn (PageSectionKey $sectie) => $sectie->value,
            array_filter(
                $sleutels,
                fn (PageSectionKey $sectie) => ! $sectie->vast() && $inhoud->gevuld($sectie),
            ),
        ));
    }
}
