<?php

namespace App\Http\Controllers;

use App\Enums\PageSectionKey;
use App\Models\Certificate;
use App\Models\Education;
use App\Models\Experience;
use App\Models\PageSection;
use App\Models\SectionHeading;
use App\Models\Service;
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

        // Zelfde regel voor de diensten. `with('points')` erbij, anders
        // haalt elke kaart zijn eigen lijstje op.
        $diensten = in_array(PageSectionKey::Diensten->value, $secties, true)
            ? Service::query()->with('points')->online()->opVolgorde()->get()
            : new Collection;

        /*
         * De certificaten en de opleidingen hangen aan hetzelfde
         * onderdeel, dus ze staan er allebei of geen van beide.
         */
        $heeftCertificaten = in_array(PageSectionKey::Certificaten->value, $secties, true);

        $certificaten = $heeftCertificaten
            ? Certificate::query()->online()->opVolgorde()->get()
            : new Collection;

        $opleidingen = $heeftCertificaten
            ? Education::query()->online()->opPeriode()->get()
            : new Collection;

        return Inertia::render('Welcome', [
            'sections' => $secties,

            /*
             * De kop bovenaan: het opschrift, de titel en de zin eronder.
             *
             * Altijd meesturen en niet alleen als het onderdeel er staat,
             * anders dan bij de tijdlijn hieronder. De kop is een vast
             * onderdeel -- hij kan niet uit en niet verplaatst worden --
             * dus de vraag "staat hij er?" bestaat hier niet.
             *
             * Zie App\Models\SectionHeading voor welke tekst terugvalt op
             * het Nederlands en welke niet.
             */
            'heroHeading' => SectionHeading::voor(PageSectionKey::Hero)->voorDeSite(),

            /*
             * Dezelfde lijst, met de labels erbij, voor het menu in de kop.
             *
             * Die stond daar hardgecodeerd, en liep dus niet mee met wat
             * de klant aan- of uitzet of versleept: een uitgezet onderdeel
             * hield zijn link naar een anker dat niet meer bestaat, en na
             * een herordening stond het menu in de oude volgorde. Nu komt
             * hij uit dezelfde bron als de pagina zelf, dus kan dat niet
             * meer uiteenlopen.
             *
             * De labels komen van de server en niet uit een tabel in de
             * frontend, want ze zijn vertaald. Zie PageSectionKey::label().
             */
            'navigation' => array_map(
                fn (string $sleutel) => [
                    'key' => $sleutel,
                    'label' => PageSectionKey::from($sleutel)->label(),
                ],
                $secties,
            ),

            /*
             * De diensten, in de taal van de bezoeker. Leeg als het
             * onderdeel niet op de pagina staat -- dan is de query
             * hierboven ook niet gedaan.
             */
            'services' => $diensten
                ->map(fn (Service $dienst) => $this->dienst($dienst))
                ->all(),

            /*
             * De kop boven dat blok. Alleen meesturen als het onderdeel
             * er staat; dezelfde regel als bij de tijdlijn hieronder.
             */
            'serviceHeading' => in_array(PageSectionKey::Diensten->value, $secties, true)
                ? SectionHeading::voor(PageSectionKey::Diensten)->voorDeSite()
                : null,

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

            /*
             * De kop boven die tijdlijn. Stond eerst in het Vue-component;
             * nu beheert de klant hem zelf, samen met de cijfers. Alleen
             * meesturen als het onderdeel er staat -- dezelfde regel als
             * hierboven.
             */
            'experienceHeading' => in_array(PageSectionKey::Ervaring->value, $secties, true)
                ? SectionHeading::voor(PageSectionKey::Ervaring)->voorDeSite()
                : null,

            /*
             * De certificaten, in de taal van de bezoeker. Leeg als het
             * onderdeel niet op de pagina staat -- dan is de query
             * hierboven ook niet gedaan.
             */
            'certificates' => $certificaten
                ->map(fn (Certificate $certificaat) => $this->certificaat($certificaat))
                ->all(),

            // De opleidingen staan in hetzelfde blok, onder het raster.
            'educations' => $opleidingen
                ->map(fn (Education $opleiding) => $this->opleiding($opleiding))
                ->all(),

            'certificateHeading' => $heeftCertificaten
                ? SectionHeading::voor(PageSectionKey::Certificaten)->voorDeSite()
                : null,
        ]);
    }

    /**
     * Eén dienst, in de taal van de bezoeker.
     *
     * Alles is hier al beslist: wat terugvalt op het Nederlands en wat
     * wordt weggelaten. De Vue-component toont alleen nog wat er is.
     * Zie App\Models\Service.
     *
     * @return array<string, mixed>
     */
    private function dienst(Service $dienst): array
    {
        return [
            'id' => $dienst->id,
            'icon' => $dienst->icon->value,
            'titel' => $dienst->titel(),
            'samenvatting' => $dienst->samenvatting(),

            // Null betekent: geen venster, en dus geen "Lees meer" op de
            // kaart. Zie DienstenSection.vue.
            'verhaal' => $dienst->verhaal(),

            'punten' => $dienst->punten(),
        ];
    }

    /**
     * Eén certificaat, in de taal van de bezoeker.
     *
     * Alles is hier al beslist: wat terugvalt op het Nederlands, wat er
     * wordt weggelaten, of de geldigheid voorbij is en hoe de datum
     * eruitziet. De Vue-component toont alleen nog wat er is. Zie
     * App\Models\Certificate.
     *
     * @return array<string, mixed>
     */
    private function certificaat(Certificate $certificaat): array
    {
        return [
            'id' => $certificaat->id,
            'naam' => $certificaat->naam(),
            'uitgever' => $certificaat->issuer,

            // Het logo van de uitgever. Staat er geen, dan valt de tegel
            // terug op een pictogram.
            'logo' => $certificaat->logo(),

            'jaar' => $certificaat->issued_on->format('Y'),
            'behaald' => $certificaat->behaald(),

            /*
             * De geldigheidsdatum gaat alleen mee zolang hij nog moet
             * komen.
             *
             * **Op de website staat nooit dat een certificaat verlopen
             * is.** Er stond eerst een label op de tegel; dat is eruit
             * gehaald, omdat een bezoeker die op een etalage kijkt niet
             * hoeft te weten dat één papiertje aan vernieuwing toe is.
             * Dan is een datum in het verleden naast "geldig tot"
             * dezelfde mededeling in andere woorden, en hoort die er
             * ook niet te zijn.
             *
             * In het beheerscherm staat het nadrukkelijk wél: daar is
             * het iets om over te beslissen. Zie CertificateController.
             */
            'geldigTot' => $certificaat->verlopen()
                ? null
                : $certificaat->geldigTot(),

            'nummer' => $certificaat->credential_id,

            // Null betekent: geen tekst in het venster. Is er ook geen
            // nummer, dan is de tegel helemaal niet aanklikbaar; zie
            // `details` hieronder.
            'toelichting' => $certificaat->toelichting(),

            'details' => $certificaat->heeftDetails(),
        ];
    }

    /**
     * Eén opleiding, in de taal van de bezoeker.
     *
     * @return array<string, mixed>
     */
    private function opleiding(Education $opleiding): array
    {
        return [
            'id' => $opleiding->id,
            'naam' => $opleiding->naam(),
            'instelling' => $opleiding->institution,
            'niveau' => $opleiding->niveau(),
            'periode' => $opleiding->periode(),
        ];
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
