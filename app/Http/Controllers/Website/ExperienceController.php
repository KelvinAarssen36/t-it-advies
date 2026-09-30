<?php

namespace App\Http\Controllers\Website;

use App\Enums\EmploymentType;
use App\Enums\ExperienceIcon;
use App\Enums\ExperienceStatKey;
use App\Enums\ExperienceStatModus;
use App\Enums\WorkplaceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Website\ExperienceRequest;
use App\Models\Experience;
use App\Models\ExperienceHeading;
use App\Models\ExperienceStat;
use App\Support\Datum;
use App\Support\Loopbaan;
use App\Support\Media\Logo;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De ervaringen op de tijdlijn: bekijken, aanmaken, wijzigen, verwijderen.
 *
 * De eerste module waarmee de klant echte inhoud beheert. Een paar dingen
 * die hier zijn beslist en die de volgende module kan overnemen:
 *
 * **Een lijst en een detailpagina, niet één lange stapel.** Het overzicht
 * is een tabel met één regel per ervaring: zoeken, scannen, doorklikken.
 * Alles wat bij één ervaring hoort staat op zijn eigen pagina. Bij een
 * loopbaan van vijfentwintig functies is een overzicht dat elk item
 * helemaal uitschrijft niet te overzien, en dan mist de klant juist het
 * item dat hij zocht.
 *
 * **Er is geen extra grendel bij verwijderen.** Bij het gebruikersbeheer
 * staat `2fa.confirm` op de wijzigende routes, want daar kun je jezelf
 * buitensluiten. Hier gaat het om inhoud: er is een dubbele bevestiging in
 * het scherm, er komt een regel in het activiteitenlogboek, en wat er stond
 * staat in die regel. Een verse authenticator-code bij elke tekstwijziging
 * zou de klant leren om zijn telefoon erbij te houden en verder niets
 * opleveren. Zie docs/security/gevoelige-acties.md.
 *
 * **De vertaling wordt niet opgeslagen door de knop.** `vertalen()` geeft
 * alleen een voorstel terug; het formulier vult daarmee de Engelse velden
 * en de klant slaat zelf op. Zie docs/architecture/automatisch-vertalen.md.
 */
class ExperienceController extends Controller
{
    /** Hoeveel regels er op één pagina van het overzicht passen. */
    private const PER_PAGINA = 15;

    public function index(Request $request, Vertaler $vertaler, Loopbaan $loopbaan): Response
    {
        $zoek = $request->string('zoek')->trim()->toString() ?: null;

        $ervaringen = Experience::query()
            ->when($zoek, fn (Builder $query, string $zoek) => $query->zoek($zoek))
            ->opTijdlijn()
            ->paginate(self::PER_PAGINA)
            ->withQueryString();

        return Inertia::render('website/Ervaring', [
            'items' => $ervaringen->through(
                fn (Experience $ervaring) => $this->rij($ervaring),
            ),

            'filters' => ['zoek' => $zoek],

            /*
             * Het verschil tussen "je hebt nog niets ingevuld" en "je
             * zoekterm levert niets op". Dat zijn twee heel verschillende
             * schermen: het eerste legt uit dat het onderdeel daarom niet
             * op de website staat, het tweede zegt alleen dat je anders
             * moet zoeken.
             *
             * Wordt er niet gezocht, dan weet de paginator het antwoord al
             * en hoeft er geen tweede query overheen.
             */
            'leeg' => $this->leeg($zoek, $ervaringen->total()),

            'opties' => $this->opties(),

            // De cijfers boven de tijdlijn, kaal: alleen wat de klant zelf
            // heeft ingesteld. Alles wat uit het soort volgt -- het woord,
            // de uitleg, wat wij zouden tellen -- staat in `cijferKeuzes`
            // en wordt in het scherm opgezocht. Zie cijferrij().
            'cijfers' => $this->cijferrij(),

            /*
             * De tekst boven de tijdlijn. Hoort bij dezelfde knop als de
             * cijfers, want op de website is het hetzelfde blok.
             */
            'kop' => $this->koptekst(),

            /*
             * De keuzelijsten voor een cijfer: welk soort het is en wat
             * ermee gebeurt. Ze komen van de server omdat de woorden
             * vertaald zijn; een lijst in de frontend zou een tweede
             * woordenlijst worden.
             */
            'cijferKeuzes' => [
                'soort' => $this->soortKeuzes($loopbaan),
                'modus' => ExperienceStatModus::keuzes(),
                'maximum' => ExperienceStat::MAXIMUM,
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    /**
     * De kop zoals het beheerscherm hem nodig heeft.
     *
     * Allebei de talen los, want dit is een bewerkvenster en geen
     * weergave: de klant vult ze allebei zelf in en moet dus zien wat er
     * in allebei staat.
     *
     * @return array<string, mixed>
     */
    private function koptekst(): array
    {
        $kop = ExperienceHeading::huidige();

        return [
            'title_nl' => $kop->title_nl,
            'title_en' => $kop->title_en,
            'intro_nl' => $kop->intro_nl,
            'intro_en' => $kop->intro_en,
            'automatisch_vertaald' => $kop->machine_translated_at !== null,
        ];
    }

    /**
     * De kop boven de tijdlijn opslaan: de tekst én de cijfers.
     *
     * **Dit is één scherm en dus één opslag.** De titel, de zin eronder en
     * de drie getallen staan op de website in hetzelfde blok; ze los
     * opslaan zou betekenen dat de klant twee keer bevestigt voor één
     * zichtbare verandering, en dat er een tussenstand kan bestaan waarin
     * de helft live staat.
     *
     * Een leeg cijferveld is hier een **betekenisvolle** waarde en geen
     * ontbrekende: het betekent "reken het zelf uit". Daarom slaat dit ook
     * `null` op in plaats van het veld over te slaan -- anders kun je een
     * ingevuld cijfer nooit meer terugzetten op automatisch.
     */
    public function kop(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            /*
             * De titel is verplicht en de inleiding niet. Zonder titel
             * staat er een blok zonder kop op de website; zonder
             * inleiding staat er gewoon geen zin onder.
             *
             * De lengtes zijn die van de kolommen, en ze zijn krap met
             * reden: dit is een kop en geen alinea. Een titel van
             * tweehonderd tekens breekt het ontwerp op een telefoon.
             */
            'title_nl' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],
            'intro_nl' => ['nullable', 'string', 'max:300'],
            'intro_en' => ['nullable', 'string', 'max:300'],

            /*
             * Of het Engels van de vertaaldienst kwam. De frontend zet
             * dit; hij weet als enige of de klant de Engelse tekst daarna
             * nog met de hand heeft aangeraakt.
             */
            'automatisch_vertaald' => ['boolean'],

            /*
             * De cijfers als lijst en niet meer als vaste drie. De klant
             * bepaalt nu zelf hoeveel het er zijn, hoe ze heten en wat er
             * met elk gebeurt.
             *
             * Hoogstens vier, en dat is een keuze over het ontwerp: vijf
             * getallen naast elkaar boven een lijst is geen samenvatting
             * meer. Nul mag ook -- dan staat er niets boven de tijdlijn,
             * en dat is een geldige keuze.
             */
            'cijfers' => ['present', 'array', 'max:'.ExperienceStat::MAXIMUM],
            'cijfers.*.key' => ['required', Rule::enum(ExperienceStatKey::class)],
            'cijfers.*.label_nl' => ['nullable', 'string', 'max:40'],
            'cijfers.*.label_en' => ['nullable', 'string', 'max:40'],
            'cijfers.*.modus' => ['required', Rule::enum(ExperienceStatModus::class)],

            // Een bovengrens, want dit staat groot op de voorpagina. Een
            // typefout van één cijfer te veel is daar meteen zichtbaar.
            'cijfers.*.waarde' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);

        $this->controleerCijfers($request, $gegevens['cijfers']);

        $veranderd = $this->bewaarCijfers($gegevens['cijfers']);

        if ($this->bewaarKoptekst($request, $gegevens)) {
            $veranderd = true;
        }

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De kop boven de tijdlijn is aangepast.'));

        return back();
    }

    /**
     * Twee regels die geen losse validatieregel kunnen zijn.
     *
     * De eerste: een **eigen** cijfer kunnen wij niet uitrekenen, dus
     * "automatisch" bestaat daar niet en er moet een getal in. De tweede:
     * van de soorten die wij wél tellen mag er maar één zijn -- twee keer
     * "jaar ervaring" boven dezelfde lijst slaat nergens op.
     *
     * @param  array<int, array<string, mixed>>  $cijfers
     */
    private function controleerCijfers(Request $request, array $cijfers): void
    {
        $gezien = [];
        $fouten = [];

        foreach ($cijfers as $index => $cijfer) {
            $soort = ExperienceStatKey::from($cijfer['key']);
            $modus = ExperienceStatModus::from($cijfer['modus']);

            if (! $soort->berekenbaar() && $modus === ExperienceStatModus::Automatisch) {
                $fouten["cijfers.{$index}.modus"] = __('Dit cijfer kunnen wij niet uitrekenen. Kies een eigen getal of zet het uit.');
            }

            if ($modus === ExperienceStatModus::Eigen && $cijfer['waarde'] === null) {
                $fouten["cijfers.{$index}.waarde"] = __('Vul een getal in, of zet dit cijfer op automatisch.');
            }

            if ($soort->berekenbaar() && in_array($soort->value, $gezien, true)) {
                $fouten["cijfers.{$index}.key"] = __('Dit cijfer staat er al een keer bij.');
            }

            $gezien[] = $soort->value;
        }

        if ($fouten !== []) {
            throw ValidationException::withMessages($fouten);
        }
    }

    /**
     * De cijfers gelijktrekken met wat het scherm stuurde.
     *
     * Wat er niet meer bij zit is weg: de klant heeft het verwijderd. Dat
     * is iets anders dan verbergen -- daar is de modus voor, en dan blijft
     * de naam en het getal bewaard.
     *
     * De volgorde komt uit de lijst zelf, want zo staat hij ook in het
     * scherm.
     *
     * @param  array<int, array<string, mixed>>  $cijfers
     * @return bool of er iets veranderd is
     */
    private function bewaarCijfers(array $cijfers): bool
    {
        $veranderd = false;

        $behouden = [];

        foreach (array_values($cijfers) as $plek => $cijfer) {
            $rij = filled($cijfer['id'] ?? null)
                ? ExperienceStat::query()->find((int) $cijfer['id'])
                : null;

            $rij ??= new ExperienceStat;

            $rij->fill([
                'key' => $cijfer['key'],
                'label_nl' => blank($cijfer['label_nl'] ?? null) ? null : $cijfer['label_nl'],
                'label_en' => blank($cijfer['label_en'] ?? null) ? null : $cijfer['label_en'],
                'modus' => $cijfer['modus'],
                'value' => $cijfer['waarde'],
                'position' => $plek,
            ]);

            if ($rij->isDirty()) {
                $veranderd = true;
            }

            $rij->save();

            $behouden[] = $rij->id;
        }

        $weg = ExperienceStat::query()->whereNotIn('id', $behouden ?: [0])->get();

        foreach ($weg as $rij) {
            $veranderd = true;
            $rij->delete();
        }

        return $veranderd;
    }

    /**
     * De titel en de zin eronder bewaren.
     *
     * @param  array<string, mixed>  $gegevens
     * @return bool of er iets veranderd is
     */
    private function bewaarKoptekst(Request $request, array $gegevens): bool
    {
        $tekst = ExperienceHeading::query()->firstOrNew([]);

        $tekst->fill([
            'title_nl' => $gegevens['title_nl'],
            'title_en' => blank($gegevens['title_en'] ?? null) ? null : $gegevens['title_en'],
            'intro_nl' => blank($gegevens['intro_nl'] ?? null) ? null : $gegevens['intro_nl'],
            'intro_en' => blank($gegevens['intro_en'] ?? null) ? null : $gegevens['intro_en'],
            'machine_translated_at' => $request->boolean('automatisch_vertaald')
                ? ($tekst->machine_translated_at ?? now())
                : null,
        ]);

        $veranderd = $tekst->isDirty();

        $tekst->save();

        return $veranderd;
    }

    /**
     * Eén ervaring, helemaal uitgeschreven.
     *
     * Hier staan allebei de talen naast elkaar, want dit is de plek waar de
     * klant controleert of zijn Engels klopt. Op het overzicht zou die
     * vergelijking niet passen; daar gaat het om terugvinden.
     */
    public function show(Experience $experience, Vertaler $vertaler): Response
    {
        return Inertia::render('website/ErvaringDetail', [
            'item' => $this->detail($experience),
            'buren' => $this->buren($experience),
            'opties' => $this->opties(),
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    public function store(ExperienceRequest $request, Logo $logo): RedirectResponse
    {
        $ervaring = Experience::query()->create([
            ...$request->gegevens(),
            'logo_path' => $this->bewaarLogo($request, $logo),
            'machine_translated_at' => $request->automatischVertaald() ? now() : null,
        ]);

        Toast::aangemaakt(
            __('De ervaring is toegevoegd.'),
            $ervaring->published
                ? __('Hij staat nu op je website.')
                : __('Hij staat nog niet op je website; zet hem online wanneer je wilt.'),
        );

        return back();
    }

    public function update(ExperienceRequest $request, Experience $experience, Logo $logo): RedirectResponse
    {
        $oudLogo = $experience->logo_path;

        $experience->update([
            ...$request->gegevens(),
            ...$this->logoVeld($request, $logo),
            'machine_translated_at' => $request->automatischVertaald() ? now() : null,
        ]);

        /*
         * Het oude bestand pas weggooien als het opslaan gelukt is, en
         * alleen als het écht is vervangen. Andersom -- eerst weg, dan
         * opslaan -- houd je bij een mislukte opslag een ervaring over die
         * naar een bestand wijst dat er niet meer is.
         */
        if ($oudLogo !== null && $experience->logo_path !== $oudLogo) {
            Storage::disk(Experience::SCHIJF)->delete($oudLogo);
        }

        // wasChanged() ná het opslaan: dat zegt of er echt iets anders is
        // geworden. Zonder deze grens meldt het scherm "aangepast" terwijl
        // er niets veranderde, en dan betekent die melding niets meer.
        if (! $experience->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De ervaring is aangepast.'));

        return back();
    }

    /**
     * Eén ervaring online of offline zetten.
     *
     * Een eigen route en niet het gewone formulier, want dit is één schuifje
     * in een lijst: het hele item meesturen om één waarde om te zetten zou
     * betekenen dat een half ingevulde rij bij elke klik opnieuw langs de
     * validatie moet.
     *
     * Wat er niet online staat, staat ook niet op de website -- en telt niet
     * mee voor de vraag of het onderdeel inhoud heeft. Zie
     * App\Models\Experience::scopeOnline.
     */
    public function online(Request $request, Experience $experience): RedirectResponse
    {
        $aan = $request->boolean('published');

        $experience->update(['published' => $aan]);

        Toast::bijgewerkt($aan
            ? __('":naam" staat nu op je website.', ['naam' => $experience->activityLabel()])
            : __('":naam" staat niet meer op je website.', ['naam' => $experience->activityLabel()]));

        return back();
    }

    public function destroy(Experience $experience): RedirectResponse
    {
        // Het label vóór het verwijderen ophalen; daarna is het object weg
        // en heeft de melding niets meer om naar te wijzen.
        $label = $experience->activityLabel();

        /*
         * Waar de klant daarna terechtkomt, moet vóór het verwijderen
         * worden bepaald: `route()` heeft het item er nog voor nodig.
         */
        $terug = $this->naHetVerwijderen($experience);

        $experience->delete();

        Toast::verwijderd(__('":naam" is verwijderd.', ['naam' => $label]));

        return redirect()->to($terug);
    }

    /**
     * Het nieuwe logo opslaan, als er een meekwam.
     *
     * Het verkleinen en het verzinnen van de bestandsnaam gebeurt in
     * App\Support\Media\Logo; hier staat alleen wanneer dat gebeurt.
     */
    private function bewaarLogo(ExperienceRequest $request, Logo $logo): ?string
    {
        $bestand = $request->logo();

        return $bestand === null
            ? null
            : $logo->bewaar(
                $bestand,
                Experience::SCHIJF,
                Experience::MAP,
                $request->uitsnede(),
            );
    }

    /**
     * Wat er bij een wijziging met `logo_path` moet gebeuren.
     *
     * Drie gevallen, en het middelste is het belangrijkste: **er kwam niets
     * mee en er is niets gevraagd, dus we laten het staan.** Zou dit veld
     * er altijd in zitten, dan raakt de klant zijn logo kwijt zodra hij een
     * typefout in de beschrijving verbetert.
     *
     * @return array<string, string|null>
     */
    private function logoVeld(ExperienceRequest $request, Logo $logo): array
    {
        $nieuw = $this->bewaarLogo($request, $logo);

        if ($nieuw !== null) {
            return ['logo_path' => $nieuw];
        }

        return $request->wilLogoWeg() ? ['logo_path' => null] : [];
    }

    /**
     * Waar je terechtkomt nadat een ervaring is verwijderd.
     *
     * Normaal is dat gewoon terug waar je vandaan kwam, mét je zoekterm en
     * je paginanummer. Behalve als je vandaan komt van de detailpagina van
     * precies dit item: die bestaat straks niet meer, en dan zou "terug"
     * een 404 zijn. Daarom vergelijken we het **pad** -- niet de hele URL,
     * want daar kan van alles achter hangen.
     */
    private function naHetVerwijderen(Experience $experience): string
    {
        $vorige = url()->previous();

        $detail = parse_url(route('website.ervaring.show', $experience), PHP_URL_PATH);

        if (parse_url($vorige, PHP_URL_PATH) === $detail) {
            return route('website.ervaring.index');
        }

        /*
         * `url()->previous()` is de Referer-header, en die komt van de
         * browser. Zonder deze controle is een omleiding naar een vreemd
         * domein mogelijk: laag risico -- er is een CSRF-token voor nodig
         * -- maar we sturen hier zelf een adres mee, en dan hoort het ook
         * ons eigen adres te zijn.
         */
        $host = parse_url($vorige, PHP_URL_HOST);

        return $host === null || $host === request()->getHost()
            ? $vorige
            : route('website.ervaring.index');
    }

    /**
     * Staat er werkelijk niets, of levert alleen de zoekterm niets op?
     *
     * @param  int  $gevonden  het aantal treffers van deze zoekopdracht
     */
    private function leeg(?string $zoek, int $gevonden): bool
    {
        if ($zoek === null) {
            return $gevonden === 0;
        }

        return ! Experience::query()->exists();
    }

    /**
     * De cijfers voor het beheerscherm: alleen wat de klant zelf instelde.
     *
     * **Hier stond eerst meer in, en dat was de fout.** Elke rij droeg ook
     * het woord van zijn soort, de uitleg erbij en wat wij voor dát soort
     * zouden tellen. Zodra de klant in het venster een ander soort koos,
     * klopte die meegestuurde bagage niet meer -- het scherm wist niet wat
     * er bij het nieuwe soort hoort en zette er nul neer. Je zag dan "nu
     * zouden wij er 0 tellen" boven een tijdlijn van vijfendertig jaar.
     *
     * Wat uit het soort volgt hoort dus niet in de rij maar bij het soort,
     * en dat staat in soortKeuzes(). Het scherm zoekt het daar op, en dan
     * klopt het bij elke keuze -- ook bij een keuze die nog niet is
     * opgeslagen.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cijferrij(): array
    {
        return ExperienceStat::query()->opVolgorde()->get()->map(
            fn (ExperienceStat $rij) => [
                'id' => $rij->id,
                'key' => $rij->key->value,

                // Leeg betekent: gebruik het standaardwoord. Dat woord
                // gaat vanzelf mee met de taal van de bezoeker.
                'label_nl' => $rij->label_nl,
                'label_en' => $rij->label_en,

                'modus' => $rij->modus->value,
                'waarde' => $rij->value,
            ],
        )->all();
    }

    /**
     * De soorten cijfer, met wat wij er nu voor zouden tellen.
     *
     * Dat getal hoort hier en niet bij de rij: het hangt aan het soort en
     * niet aan de instelling van de klant. Zo kan het scherm bij elke
     * keuze in de lijst laten zien wat automatisch zou opleveren, ook
     * voordat er iets is opgeslagen.
     *
     * Een eigen cijfer krijgt `null`: dat kunnen wij niet tellen, en nul
     * zou suggereren dat we het geprobeerd hebben.
     *
     * @return array<int, array<string, mixed>>
     */
    private function soortKeuzes(Loopbaan $loopbaan): array
    {
        $berekend = $loopbaan->berekend($loopbaan->online());

        return array_map(
            fn (array $keuze) => [
                ...$keuze,
                'berekenbaar' => ExperienceStatKey::from($keuze['value'])->berekenbaar(),
                'berekend' => $berekend[$keuze['value']] ?? null,
            ],
            ExperienceStatKey::keuzes(),
        );
    }

    /**
     * De keuzelijsten van het formulier.
     *
     * Het overzicht en de detailpagina hebben allebei hetzelfde
     * bewerkvenster, dus allebei deze lijsten. Eén methode, zodat er nooit
     * een lijst op het ene scherm bijkomt en op het andere niet.
     *
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    private function opties(): array
    {
        return [
            'icon' => ExperienceIcon::opties(),
            'employment' => EmploymentType::opties(),
            'workplace' => WorkplaceType::opties(),
            'maanden' => $this->maanden(),
            'jaren' => $this->jaren(),
        ];
    }

    /**
     * De ervaring ervoor en erna op de tijdlijn.
     *
     * Hiermee loop je een loopbaan na zonder telkens terug te hoeven naar
     * het overzicht -- precies wat je doet als je de Engelse teksten
     * controleert.
     *
     * De hele volgorde wordt opgehaald en niet met een slimme query om de
     * buren heen gerekend. Dat kan, omdat het om een loopbaan gaat: een
     * paar tientallen regels van drie kolommen. Een venster-query zou hier
     * de sorteervolgorde uit `scopeOpTijdlijn` op een tweede plek moeten
     * herhalen, en dan lopen die twee vroeg of laat uit elkaar.
     *
     * @return array<string, array<string, mixed>|null>
     */
    private function buren(Experience $experience): array
    {
        /*
         * De datums gaan mee omdat de periode op de knop staat. Zonder dat
         * zie je alleen een functietitel, en bij iemand die drie keer
         * "Service Manager" is geweest zegt dat niets over waar je heen
         * gaat.
         */
        $volgorde = Experience::query()
            ->opTijdlijn()
            ->get(['id', 'role_nl', 'organisation', 'started_on', 'ended_on'])
            ->values();

        $plek = $volgorde->search(fn (Experience $rij) => $rij->id === $experience->id);

        // Kan alleen bij een wedloop: het item is weg tussen het ophalen en
        // nu. Dan is geen navigatie beter dan een verkeerde.
        if ($plek === false) {
            return ['vorige' => null, 'volgende' => null];
        }

        $buur = function (int $plek) use ($volgorde): ?array {
            $rij = $plek < 0 ? null : $volgorde->get($plek);

            return $rij === null ? null : [
                'id' => $rij->id,
                'functie' => $rij->role_nl,
                'organisatie' => $rij->organisation,
                'periode' => $rij->periode(),
            ];
        };

        return [
            'vorige' => $buur($plek - 1),
            'volgende' => $buur($plek + 1),
        ];
    }

    /**
     * De twaalf maanden, in de taal van het portaal.
     *
     * Die namen komen van de server en niet uit `Intl` in de browser, om
     * dezelfde reden als alle andere datums: anders hangt de taal van de
     * lijst af van het besturingssysteem van de bezoeker in plaats van van
     * de taal die hij in het portaal heeft gekozen. Zie
     * docs/architecture/vertalingen.md.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function maanden(): array
    {
        return array_map(
            fn (int $maand) => [
                'value' => str_pad((string) $maand, 2, '0', STR_PAD_LEFT),
                'label' => (string) CarbonImmutable::create(2000, $maand, 1)?->isoFormat('MMMM'),
            ],
            range(1, 12),
        );
    }

    /**
     * De jaren waaruit je kunt kiezen: van dit jaar tot zestig jaar terug.
     *
     * Aflopend, want je voert meestal eerst je huidige functie in. Een
     * lijst die bij 1965 begint laat je elke keer helemaal doorscrollen.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function jaren(): array
    {
        $nu = (int) now()->format('Y');

        return array_map(
            fn (int $jaar) => ['value' => (string) $jaar, 'label' => (string) $jaar],
            range($nu, $nu - 60),
        );
    }

    /**
     * Eén ervaring zoals het beheerscherm hem toont.
     *
     * De opgemaakte periode gaat mee als kant-en-klare tekst, want datums
     * worden op de server opgemaakt en niet in de browser -- anders hangt
     * de uitkomst af van de taal van het besturingssysteem in plaats van
     * die van het portaal. Zie docs/architecture/vertalingen.md.
     *
     * De losse `_nl`- en `_en`-waarden gaan óók mee: het formulier bewerkt
     * allebei de talen en heeft ze dus allebei nodig. Dat geldt ook op het
     * overzicht, want het bewerkvenster gaat daar rechtstreeks open.
     *
     * @return array<string, mixed>
     */
    private function rij(Experience $ervaring): array
    {
        return [
            'id' => $ervaring->id,
            'icon' => $ervaring->icon->value,
            'published' => $ervaring->published,

            // De URL en niet het pad: het scherm hoeft niet te weten waar de
            // bestanden staan. Zie App\Models\Experience::logo.
            'logo' => $ervaring->logo(),

            'role_nl' => $ervaring->role_nl,
            'role_en' => $ervaring->role_en,
            'organisation' => $ervaring->organisation,
            'organisation_url' => $ervaring->organisation_url,
            'employment' => $ervaring->employment?->value,
            'workplace' => $ervaring->workplace?->value,
            'location_nl' => $ervaring->location_nl,
            'location_en' => $ervaring->location_en,
            'description_nl' => $ervaring->description_nl,
            'description_en' => $ervaring->description_en,

            // Als "2021-03", de vorm die het formulier verwacht.
            'started_on' => $ervaring->started_on->format('Y-m'),
            'ended_on' => $ervaring->ended_on?->format('Y-m'),

            'periode' => $ervaring->periode(),
            'duur' => $ervaring->duur(),
            'loopt' => $ervaring->loopt(),

            /*
             * Of er nog een Engelse vertaling ontbreekt. Alleen de
             * verplichte functietitel telt: is die er, dan is het item
             * bruikbaar op de Engelse site. Een lege beschrijving is geen
             * ontbrekende vertaling maar een lege beschrijving.
             */
            'vertaald' => filled($ervaring->role_en),
            'automatisch_vertaald' => $ervaring->machine_translated_at !== null,
            'vertaald_op' => Datum::dag($ervaring->machine_translated_at),
        ];
    }

    /**
     * Dezelfde ervaring, met wat de detailpagina er extra bij nodig heeft.
     *
     * De labels worden hier opgezocht en niet in de browser. Het overzicht
     * heeft ze niet nodig -- daar staat alleen de sleutel -- en een scherm
     * dat zelf in de keuzelijst gaat zoeken is een scherm dat stilvalt
     * zodra iemand een waarde uit de enum haalt.
     *
     * `website()` en niet `organisation_url`: dat is dezelfde controle als
     * op de publieke site, en om dezelfde reden. Vue schoont een `:href`
     * niet. Zie App\Models\Experience::website.
     *
     * @return array<string, mixed>
     */
    private function detail(Experience $ervaring): array
    {
        return [
            ...$this->rij($ervaring),

            'icon_label' => $ervaring->icon->label(),
            'employment_label' => $ervaring->employment?->label(),
            'workplace_label' => $ervaring->workplace?->label(),
            'website' => $ervaring->website(),
            'gewijzigd' => Datum::dag($ervaring->updated_at),
        ];
    }
}
