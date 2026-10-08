<?php

namespace App\Http\Controllers;

use App\Enums\ContactWeergave;
use App\Enums\PageSectionKey;
use App\Models\AboutSetting;
use App\Models\Certificate;
use App\Models\ContactSetting;
use App\Models\Education;
use App\Models\Experience;
use App\Models\FaqItem;
use App\Models\Project;
use App\Models\SectionHeading;
use App\Models\Service;
use App\Models\Statistic;
use App\Models\WorkStep;
use App\Support\Contact\Contactformulier;
use App\Support\Loopbaan;
use App\Support\Page\Navigatie;
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
    public function __invoke(
        Navigatie $navigatie,
        Loopbaan $loopbaanCijfers,
        Contactformulier $formulier,
    ): Response {
        $secties = $navigatie->sleutels();

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

        $stappen = in_array(PageSectionKey::Werkwijze->value, $secties, true)
            ? WorkStep::query()->online()->opVolgorde()->get()
            : new Collection;

        $statistieken = in_array(PageSectionKey::Statistieken->value, $secties, true)
            ? Statistic::query()->online()->opVolgorde()->get()
            : new Collection;

        /*
         * De etalage. Twee stukken uit dezelfde lijst: het uitgelichte
         * project en de eerstvolgende paar daarna.
         *
         * **Hoogstens vier kaarten op de voorpagina, en de rest achter
         * een knop.** Een etalage die met elk project langer wordt maakt
         * de voorpagina op den duur onleesbaar; `/projecten` heeft geen
         * grens en toont ze allemaal.
         */
        $aan = in_array(PageSectionKey::Projecten->value, $secties, true);

        /*
         * De uitgelichte projecten. Staat er meer dan een ster, dan draait
         * het blok hierboven als slideshow -- precies zoals op
         * `/projecten`, zodat de twee plekken hetzelfde doen.
         */
        $uitgelicht = $aan ? Project::uitgelichte() : new Collection;

        $projecten = $aan
            ? Project::query()
                ->online()
                ->where('featured', false)
                ->opVolgorde()
                ->limit(Project::OP_DE_VOORPAGINA)
                ->get()
            : new Collection;

        /*
         * Of er nog meer zijn dan wat hier staat. Als getal en niet als
         * lijst: de knop "Bekijk alle projecten" hoeft alleen te weten
         * dát er meer is.
         */
        $projectenTotaal = $aan ? Project::query()->online()->count() : 0;

        $vragen = in_array(PageSectionKey::Faq->value, $secties, true)
            ? FaqItem::query()->online()->opVolgorde()->get()
            : new Collection;

        /*
         * Het korte stuk over de eigenaar.
         *
         * `voorDeSite()` geeft `null` zodra er geen samenvatting in de taal
         * van de bezoeker staat. Dat kan hier niet gebeuren -- de teller in
         * AppServiceProvider heeft het onderdeel dan al uit `$secties`
         * gehouden -- maar het staat er zodat de ene plek de andere niet
         * hoeft te vertrouwen.
         */
        $overMij = in_array(PageSectionKey::OverMij->value, $secties, true)
            ? AboutSetting::huidige()->voorDeSite()
            : null;

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
            'navigation' => $navigatie->menu($secties),

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

            /*
             * De werkwijze. Stond tot voor kort in het Vue-bestand zelf;
             * nu komt hij hiervandaan, inclusief de kop erboven.
             */
            'workSteps' => $stappen
                ->map(fn (WorkStep $stap) => $stap->voorDeSite())
                ->all(),

            'workHeading' => in_array(PageSectionKey::Werkwijze->value, $secties, true)
                ? SectionHeading::voor(PageSectionKey::Werkwijze)->voorDeSite()
                : null,

            /*
             * Of de pagina /werkwijze bestaat. Als getal noch als lijst
             * maar als ja-of-nee: de knop ernaartoe hoeft alleen te weten
             * dát er meer te lezen valt. De regel staat in
             * PublicWerkwijzeController, zodat de knop en de pagina niet
             * uit elkaar kunnen lopen.
             */
            'workPage' => PublicWerkwijzeController::ietsTeLezen($stappen->all()),

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

            /*
             * De statistieken, al ingedeeld in groepen en per groep
             * gebundeld op weergave. Dat rekenwerk staat hier en niet
             * in Vue, om dezelfde reden als de taalkeuze: het is een
             * inhoudelijke beslissing en geen opmaak, en het component
             * hoort alleen nog te tekenen wat het krijgt.
             */
            'statistics' => $this->statistiekGroepen($statistieken),

            'statisticHeading' => in_array(PageSectionKey::Statistieken->value, $secties, true)
                ? SectionHeading::voor(PageSectionKey::Statistieken)->voorDeSite()
                : null,

            /*
             * Het korte stuk over de eigenaar, en de kop erboven.
             *
             * Allebei `null` als het onderdeel er niet staat, zoals bij de
             * andere blokken. In `about` zit ook of de aparte pagina
             * klaarstaat; dat bepaalt of er een knop onder komt. Eén bron,
             * zodat de knop en de route niet uiteen kunnen lopen -- zie
             * AboutSetting::paginaStaatKlaar().
             */
            'about' => $overMij,

            'aboutHeading' => in_array(PageSectionKey::OverMij->value, $secties, true)
                ? SectionHeading::voor(PageSectionKey::OverMij)->voorDeSite()
                : null,

            /*
             * De veelgestelde vragen.
             *
             * **Álle vragen gaan mee, ook die buiten de eerste
             * bladzijde vallen.** Het blok bladert per zes, en het zou
             * schelen om alleen die zes te sturen -- maar dan bestaat de
             * rest niet voor een zoekmachine, en juist bij een
             * vragenlijst is dat zonde: vragen zijn precies waarop
             * gezocht wordt.
             *
             * Het blok zet de vragen buiten de huidige bladzijde op
             * `hidden` in plaats van ze weg te laten, dus ze staan in de
             * DOM -- of die nu door de SSR-server of door de browser
             * wordt opgebouwd. En het briefje hieronder staat los
             * daarvan in de `<head>`, dus de vragen zijn ook te vinden
             * zonder dat er JavaScript draait.
             *
             * Zie docs/architecture/modules/faq.md.
             */
            /*
             * De projecten, al opgemaakt in de taal van de bezoeker. Zie
             * docs/architecture/modules/projecten.md.
             */
            // Een lijst en geen enkel project: staat er meer dan een
            // ster, dan draait het blok als slideshow.
            'featuredProjects' => $uitgelicht
                ->map(fn (Project $project) => $project->voorDeKaart())
                ->all(),

            'projects' => $projecten
                ->map(fn (Project $project) => $project->voorDeKaart())
                ->all(),

            'projectsTotal' => $projectenTotaal,

            'projectHeading' => in_array(PageSectionKey::Projecten->value, $secties, true)
                ? SectionHeading::voor(PageSectionKey::Projecten)->voorDeSite()
                : null,

            'faq' => $vragen
                ->map(fn (FaqItem $vraag) => $vraag->voorDeSite())
                ->all(),

            'faqHeading' => in_array(PageSectionKey::Faq->value, $secties, true)
                ? SectionHeading::voor(PageSectionKey::Faq)->voorDeSite()
                : null,

            /*
             * Het contactformulier.
             *
             * **De velden komen van de server en staan niet in de Vue.**
             * De eigenaar bepaalt welke er staan en welke moeten, en die
             * lijst moet dezelfde zijn als waarop gevalideerd wordt --
             * anders krijgt een bezoeker een foutmelding over een veld dat
             * hij niet ziet. Eén bron: `Contactformulier`.
             *
             * `null` als het onderdeel niet op de pagina staat, of als de
             * eigenaar heeft gekozen voor een eigen contactpagina. Dan
             * staat er op de landing een knop en geen formulier.
             */
            'contact' => $this->contactblok($secties, $formulier),
        ])->withViewData([
            /*
             * De vragen als structuurdata, voor de zoekmachines.
             *
             * **Via de rootview en niet via Vue.** Dit hoort in de `<head>`,
             * en Inertia's `Head`-component laat zo'n tag er niet door --
             * dat is nagemeten. Belangrijker: het is een beslissing over
             * inhoud, en die horen in dit project op de server te staan.
             *
             * Zie het kopje erover in docs/architecture/modules/faq.md,
             * inclusief wat dit sinds 2023 níet meer oplevert.
             */
            'vragenBriefje' => $this->vragenBriefje($vragen),
        ]);
    }

    /**
     * De vragenlijst als `FAQPage`-structuurdata, of `null`.
     *
     * **`JSON_HEX_TAG` is hier geen opsmuk maar de beveiliging.** Deze
     * tekst komt tussen `<script>`-tags te staan, en de eigenaar typt hem
     * zelf. Zou er `</script>` in een antwoord staan, dan sluit dat de tag
     * en is alles erna gewone HTML. Met deze vlag wordt elke `<` een
     * `<`: geldige JSON, en niets dat een tag kan afbreken.
     *
     * `null` zodra er geen vragen zijn, en dan staat er niets in de head --
     * een lege lijst opgeven is erger dan hem weglaten.
     *
     * @param  Collection<int, FaqItem>  $vragen
     */
    private function vragenBriefje(Collection $vragen): ?string
    {
        if ($vragen->isEmpty()) {
            return null;
        }

        return json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => $vragen->map(fn (FaqItem $vraag) => [
                '@type' => 'Question',
                'name' => $vraag->vraag(),
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => $vraag->antwoord(),
                ],
            ])->all(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?: null;
    }

    /**
     * Wat de contactsectie op de landingspagina nodig heeft.
     *
     * @param  array<int, string>  $secties
     * @return array<string, mixed>|null
     */
    private function contactblok(array $secties, Contactformulier $formulier): ?array
    {
        if (! in_array(PageSectionKey::Contact->value, $secties, true)) {
            return null;
        }

        $instellingen = ContactSetting::huidige();
        $eigenPagina = $instellingen->display === ContactWeergave::EigenPagina;

        return [
            'kop' => SectionHeading::voor(PageSectionKey::Contact)->voorDeSite(),

            /*
             * Bij een eigen contactpagina staat hier alleen een knop, dus
             * dan hoeven de velden niet mee. Dat is niet alleen zuinig:
             * het formulier staat dan op één plek, en een tweede kopie op
             * de landing zou een tweede Turnstile-widget betekenen.
             */
            'eigenPagina' => $eigenPagina,
            'velden' => $eigenPagina ? [] : $formulier->voorDeSite(),
            'onderwerpen' => $eigenPagina ? [] : $formulier->onderwerpenVoorDeSite(),
            'eigenOnderwerpToegestaan' => $formulier->eigenOnderwerpToegestaan(),
            'instellingen' => $formulier->versie(),

            /*
             * Het publieke adres, als uitweg onder het formulier. Het
             * tokenveld van Turnstile is verplicht, dus laadt Cloudflare
             * niet dan is het formulier niet te versturen -- en dan hoort
             * er een andere manier te staan om iemand te bereiken. Zie
             * ContactFormulier.vue.
             */
            'email' => config('site.email'),
        ];
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
     * De statistieken, ingedeeld in groepen en gebundeld op weergave.
     *
     * **Twee beslissingen die hier vallen en niet in Vue.**
     *
     * De eerste is de **indeling**: groeperen gebeurt op het Nederlandse
     * veld, ook voor een Engelse bezoeker. Zou je op de vertaalde waarde
     * groeperen, dan valt een groep in het Engels uit elkaar zodra één
     * item zijn Engelse groepsnaam mist -- en dan staat dezelfde site in
     * twee talen anders ingedeeld. Zie `Statistic::groepSleutel()`.
     *
     * De tweede is de **bundeling**: binnen een groep worden
     * opeenvolgende statistieken van dezelfde vorm samen op één rij
     * gezet. Een ring naast een balk naast een teller ziet er kapot uit
     * -- drie totaal verschillende vormen en hoogtes in één raster --
     * dus elke vorm krijgt zijn eigen band.
     *
     * **Opeenvolgend, en dat is een correctie.** Hier stonden eerst drie
     * vaste bakken: eerst alle ringen, dan alle tellers, dan alle
     * balken. Dat zag er altijd netjes uit, maar het maakte het slepen
     * onbegrijpelijk: sleepte de eigenaar een balk boven een ring, dan
     * gebeurde er op zijn website niets zichtbaars -- de ring stond
     * immers altijd eerst. Hij zag het in de tabel veranderen en op de
     * pagina niet, en dan is de sleepgreep een knop die liegt.
     *
     * Nu volgt de uitkomst zijn volgorde wél, en wordt er alleen een
     * nieuwe band begonnen zodra de vorm verandert. Zet hij ring, ring,
     * balk, ring neer, dan krijgt hij een rij ringen, een balk, en weer
     * een ring. Nooit twee vormen op één rij, en nooit een sleepgreep
     * die niets doet.
     *
     * Wat hij met slepen níet kan is een statistiek uit zijn groep
     * halen; daar is het groepsveld voor.
     *
     * Een groep zonder naam komt vooraan, boven het eerste kopje. Dat is
     * waar losse cijfers horen: ze zijn niet minder belangrijk, ze
     * horen alleen nergens bij.
     *
     * @param  Collection<int, Statistic>  $statistieken
     * @return array<int, array<string, mixed>>
     */
    private function statistiekGroepen(Collection $statistieken): array
    {
        /*
         * Eerst alleen indelen, en in een tweede stap in banden
         * verdelen. Dat kon ook in één keer met een referentie naar de
         * band waar we mee bezig zijn, en dat stond er eerst -- maar
         * dan is niet te lezen wat er gebeurt, en PHPStan kon de
         * mutatie door die referentie niet volgen.
         *
         * @var array<string, array{naam: string|null, items: array<int, Statistic>}>
         */
        $perGroep = [];

        foreach ($statistieken as $statistiek) {
            $sleutel = $statistiek->groepSleutel();

            $perGroep[$sleutel] ??= [
                'naam' => $statistiek->groep(),
                'items' => [],
            ];

            $perGroep[$sleutel]['items'][] = $statistiek;
        }

        /*
         * De naamloze groep vooraan, de rest in de volgorde waarin hun
         * eerste item staat. Daarmee bepaalt de klant met slepen ook de
         * volgorde van de groepen, zonder dat daar een apart scherm
         * voor nodig is.
         *
         * Dat `0` teruggeven de bestaande volgorde bewaart is geen
         * toeval maar een garantie: sinds PHP 8.0 zijn de sorteringen
         * stabiel. Op een oudere versie zou de volgorde van de groepen
         * willekeurig zijn.
         *
         * **De parameters zijn `int|string` en dat is geen slordigheid.**
         * PHP maakt van een array-sleutel die eruitziet als een getal
         * stilletjes een integer, dus een groep die de klant "2024"
         * noemt komt hier als `int` binnen. Zonder `declare(strict_types)`
         * valt dat nu niet op -- PHP maakt er weer een string van -- maar
         * de dag dat iemand dat wél aanzet ligt de voorpagina eruit op
         * een groepsnaam. Zie StatisticGroupTest.
         */
        uksort($perGroep, fn (int|string $een, int|string $twee) => match (true) {
            (string) $een === '' => -1,
            (string) $twee === '' => 1,
            default => 0,
        });

        $uitkomst = [];

        foreach ($perGroep as $sleutel => $groep) {
            $uitkomst[] = [
                'sleutel' => (string) $sleutel,
                'naam' => $groep['naam'],
                'bundels' => $this->statistiekBundels($groep['items']),
            ];
        }

        return $uitkomst;
    }

    /**
     * De statistieken van één groep in banden verdelen.
     *
     * Een band is een rij opeenvolgende statistieken van dezelfde vorm.
     * Verandert de vorm, dan begint er een nieuwe -- en dat is de hele
     * regel. Zet de klant ring, ring, balk, ring neer, dan zijn dat dus
     * drie banden en staat zijn volgorde er precies zo op de site.
     *
     * Zo komen twee vormen nooit op één rij, en doet slepen altijd iets
     * zichtbaars. Zie de toelichting bij `statistiekGroepen()`.
     *
     * @param  array<int, Statistic>  $items
     * @return array<int, array{weergave: string, items: array<int, array<string, mixed>>}>
     */
    private function statistiekBundels(array $items): array
    {
        $bundels = [];

        foreach ($items as $statistiek) {
            $vorm = $statistiek->display->value;
            $laatste = count($bundels) - 1;

            if ($laatste >= 0 && $bundels[$laatste]['weergave'] === $vorm) {
                $bundels[$laatste]['items'][] = $this->statistiek($statistiek);

                continue;
            }

            $bundels[] = [
                'weergave' => $vorm,
                'items' => [$this->statistiek($statistiek)],
            ];
        }

        return $bundels;
    }

    /**
     * Eén statistiek, in de taal van de bezoeker.
     *
     * @return array<string, mixed>
     */
    private function statistiek(Statistic $statistiek): array
    {
        return [
            'id' => $statistiek->id,
            'naam' => $statistiek->naam(),
            'waarde' => $statistiek->value,
            'voor' => $statistiek->prefix,
            'na' => $statistiek->suffix,
            'notitie' => $statistiek->notitie(),
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
}
