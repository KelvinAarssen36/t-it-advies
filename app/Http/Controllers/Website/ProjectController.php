<?php

namespace App\Http\Controllers\Website;

use App\Enums\PageSectionKey;
use App\Enums\ProjectType;
use App\Enums\ProjectWeergave;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Http\Requests\Website\ProjectRequest;
use App\Models\Project;
use App\Models\ProjectSetting;
use App\Support\Datum;
use App\Support\Media\Logo;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het onderdeel "Projecten": de etalage van de eigenaar.
 *
 * Volgt het patroon van de certificaten -- een lijst met een eigen
 * volgorde, een beeld per item en een beheerbare kop erboven -- met twee
 * dingen erbij: **uitlichten** (zie `uitlichten()`) en **de weergave van
 * de projectenpagina** (zie `weergave()`).
 *
 * | Handeling      | Wat het doet                                   |
 * | -------------- | ---------------------------------------------- |
 * | `index()`      | Het overzicht met de kaarttabel                |
 * | `store()`      | Een project erbij, achteraan in de rij         |
 * | `update()`     | Wijzigen; de slug blijft staan                 |
 * | `destroy()`    | Weg, met het beeld mee                         |
 * | `online()`     | Het schuifje, zonder de rest te valideren      |
 * | `uitlichten()` | Dit project vooraan, de rest niet meer         |
 * | `volgorde()`   | Slepen                                         |
 * | `kop()`        | De tekst boven het blok                        |
 *
 * Zie docs/architecture/modules/projecten.md.
 */
class ProjectController extends Controller
{
    use BewaartKoptekst;

    public function index(Vertaler $vertaler): Response
    {
        $projecten = Project::query()->opVolgorde()->get();

        return Inertia::render('website/Projecten', [
            'items' => $projecten
                ->map(fn (Project $project) => $project->voorHetScherm())
                ->all(),

            'leeg' => $projecten->isEmpty(),

            'kop' => $this->koptekst()->voorHetScherm(),

            // Hoe de pagina met alle projecten eruitziet; het venster
            // "Weergave" zet dit om.
            'weergave' => ProjectSetting::weergave()->value,

            'opties' => [
                'soorten' => ProjectType::opties(),
                'weergaven' => ProjectWeergave::opties(),
                'maanden' => Datum::maanden(),
                'jaren' => Datum::jaren(),

                // Dezelfde grenzen als de validatie, zodat het scherm
                // geen teller kan tonen die de server niet kent.
                'samenvattingMax' => ProjectRequest::SAMENVATTING_MAX,
                'tekstMax' => ProjectRequest::TEKST_MAX,

                // Hoeveel er naast het uitgelichte op de voorpagina staan;
                // het scherm zegt dat erbij zodat de volgorde ergens over gaat.
                'opDeVoorpagina' => Project::OP_DE_VOORPAGINA,
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    /**
     * Hoe de pagina met alle projecten eruitziet.
     *
     * **Een instelling en geen keuze van ons.** Welke vorm beter werkt
     * hangt af van wat er over een jaar in staat: het ritme leeft van de
     * afbeeldingen, het raster werkt juist als die er niet zijn. Dat weet
     * de eigenaar beter dan wij. Zie `App\Enums\ProjectWeergave`.
     *
     * Eén waarde omzetten, dus een eigen route naast `online` en
     * `uitlichten` en niet een veld in het projectformulier.
     */
    public function weergave(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'weergave' => ['required', Rule::enum(ProjectWeergave::class)],
        ]);

        $gekozen = ProjectWeergave::from($gegevens['weergave']);
        $instellingen = ProjectSetting::huidige();

        if ($instellingen->exists && $instellingen->layout === $gekozen) {
            Toast::melding(__('Die weergave stond al ingesteld.'));

            return back();
        }

        /*
         * `save()` op het exemplaar uit `huidige()`: dat is de bestaande
         * rij, of een nieuwe met de standaardwaarden als er nog nooit is
         * geseed. Zo hoeft hier geen `updateOrCreate` met een lege sleutel.
         */
        $instellingen->layout = $gekozen;
        $instellingen->save();

        Toast::bijgewerkt(
            __('Je projectenpagina staat nu op ":weergave".', ['weergave' => $gekozen->label()]),
        );

        return back();
    }

    public function store(ProjectRequest $request, Logo $logo): RedirectResponse
    {
        $gegevens = $request->gegevens();

        $project = Project::query()->create([
            ...$gegevens,

            /*
             * Het adres wordt hier gemaakt en daarna nooit meer. Zie
             * Project::vrijeSlug() voor waarom een titelwijziging het
             * adres met rust laat.
             */
            'slug' => Project::vrijeSlug($gegevens['title_nl']),

            'image_path' => $this->bewaarBeeld($request, $logo),

            // Achteraan in de rij. Waar het komt te staan bepaalt de
            // klant daarna met slepen.
            'position' => (int) Project::query()->max('position') + 1,

            'machine_translated_at' => $request->automatischVertaald()
                ? Carbon::now()
                : null,
        ]);

        Toast::aangemaakt(
            __('Het project is toegevoegd.'),
            $project->published
                ? __('Het staat nu op je website.')
                : __('Het staat nog niet op je website; zet het online wanneer je wilt.'),
        );

        return back();
    }

    public function update(
        ProjectRequest $request,
        Project $project,
        Logo $logo,
    ): RedirectResponse {
        $oudBeeld = $project->image_path;

        $project->update([
            ...$request->gegevens(),
            ...$this->beeldVeld($request, $logo),

            /*
             * Het merkje "automatisch vertaald" hangt aan de tekst en
             * niet aan deze opslag: het zegt dat het Engels dat er nú
             * staat van de dienst komt en nog door niemand is nagelezen.
             */
            'machine_translated_at' => $request->automatischVertaald()
                ? ($project->machine_translated_at ?? Carbon::now())
                : null,
        ]);

        /*
         * Het oude bestand pas weggooien als het opslaan is gelukt, en
         * alleen als het écht is vervangen. Andersom houd je bij een
         * mislukte opslag een rij over die naar een bestand wijst dat er
         * niet meer is.
         */
        if ($oudBeeld !== null && $project->image_path !== $oudBeeld) {
            Storage::disk(Project::SCHIJF)->delete($oudBeeld);
        }

        if (! $project->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('Het project is aangepast.'));

        return back();
    }

    public function destroy(Project $project): RedirectResponse
    {
        // De titel vóór het verwijderen ophalen; daarna is hij weg. Het
        // beeld gaat mee via de haak op het model.
        $titel = $project->title_nl;

        /*
         * Stond dit project uitgelicht, dan is er daarna geen uitgelicht
         * project meer -- en dat is met opzet. Stil een ander project
         * vooraan zetten is iets kiezen wat de eigenaar niet heeft
         * gekozen; zie uitlichten().
         */
        $wasUitgelicht = $project->featured;

        $project->delete();

        Toast::verwijderd(
            __('":naam" is verwijderd.', ['naam' => $titel]),
            $wasUitgelicht
                ? (string) __('Er staat nu geen project meer uitgelicht op je voorpagina.')
                : null,
        );

        return back();
    }

    /**
     * Het schuifje online/offline.
     *
     * Een eigen route, omdat één waarde omzetten niet het hele formulier
     * langs de validatie hoort te sturen.
     *
     * **Een uitgelicht project offline zetten mag.** De vlag blijft dan
     * gewoon staan; het prominente blok verdwijnt alleen van de website
     * tot het weer online komt. Zie Project::uitgelichte().
     */
    public function online(Request $request, Project $project): RedirectResponse
    {
        $aan = $request->boolean('published');

        $project->update(['published' => $aan]);

        $waarschuwing = ! $aan && $project->featured
            ? (string) __('Dit project stond uitgelicht, dus dat blok staat nu niet meer op je voorpagina.')
            : null;

        Toast::bijgewerkt(
            $aan
                ? __('":naam" staat nu op je website.', ['naam' => $project->title_nl])
                : __('":naam" staat niet meer op je website.', ['naam' => $project->title_nl]),
            $waarschuwing,
        );

        return back();
    }

    /**
     * Dit project uitlichten, of het uitlichten ongedaan maken.
     *
     * **Er mogen er meerdere tegelijk uitgelicht staan.** Op
     * `/projecten` draaien ze als slideshow over de volle breedte; op de
     * voorpagina staat het eerste groot en de rest gewoon als kaart.
     * Welke het eerste is bepaalt de volgorde die de eigenaar zelf
     * sleept.
     *
     * **Eén schakelaar per project, geen keuzelijst bovenaan.** Je zet
     * hem aan op de plek waar je het project ziet staan, en nog een keer
     * drukken zet hem uit. Daarom is dit een `PATCH` naast `online` en
     * niet een veld in het formulier: één waarde omzetten hoort niet het
     * hele formulier langs de validatie te sturen.
     *
     * **De volgorde blijft staan.** Uitlichten en slepen zijn twee
     * verschillende dingen: een project dat je vooraan zet hoort zijn
     * plek in de lijst te houden voor als je het later weer gewoon
     * tussen de andere wilt.
     */
    public function uitlichten(Project $project): RedirectResponse
    {
        $aan = ! $project->featured;

        $project->update(['featured' => $aan]);

        if (! $aan) {
            Toast::bijgewerkt(
                __('":naam" staat niet meer uitgelicht.', ['naam' => $project->title_nl]),
                Project::query()->where('featured', true)->exists()
                    ? null
                    : (string) __('Er staat nu geen enkel project uitgelicht, dus op je website begint de projectenpagina met de lijst.'),
            );

            return back();
        }

        Toast::bijgewerkt(
            __('":naam" staat nu uitgelicht.', ['naam' => $project->title_nl]),
            $project->published
                ? null
                : (string) __('Het staat offline, dus op je website zie je het pas als je het online zet.'),
        );

        return back();
    }

    /**
     * De volgorde van de projecten.
     *
     * Opnieuw nummeren vanaf 1 en niet verschuiven, hetzelfde patroon als
     * bij de certificaten: dan kan er geen gat of dubbele positie
     * ontstaan, hoe vaak je ook sleept.
     */
    public function volgorde(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'projecten' => ['required', 'array'],
            'projecten.*.id' => ['required', 'integer', 'exists:projects,id'],
        ]);

        /** @var array<int, array{id: int}> $rij */
        $rij = $gegevens['projecten'];

        /*
         * De hele lijst moet meekomen, niet een deel ervan. Zou een
         * verzoek met drie van de zes ids binnenkomen, dan krijgen die
         * drie positie 1, 2 en 3 en botsen ze met de andere drie.
         */
        $bestaand = Project::query()->orderBy('id')->pluck('id')->all();
        $gekregen = array_map(fn (array $regel) => $regel['id'], $rij);
        sort($gekregen);

        if ($gekregen !== $bestaand) {
            Toast::fout(__('De lijst klopt niet meer. Ververs de pagina en probeer het opnieuw.'));

            return back();
        }

        $veranderd = DB::transaction(function () use ($rij): bool {
            $veranderd = false;

            foreach ($rij as $index => $regel) {
                $project = Project::query()->findOrFail($regel['id']);
                $project->position = $index + 1;

                if ($project->isDirty()) {
                    $veranderd = true;
                    $project->save();
                }
            }

            return $veranderd;
        });

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De volgorde van je projecten is aangepast.'));

        return back();
    }

    /** De kop boven het hele blok. */
    public function kop(Request $request): RedirectResponse
    {
        if (! $this->verwerkKoptekst($request)) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De kop boven je projecten is aangepast.'));

        return back();
    }

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::Projecten;
    }

    /**
     * Het beeld bij het aanmaken: er is nog niets om te behouden.
     */
    private function bewaarBeeld(ProjectRequest $request, Logo $logo): ?string
    {
        $bestand = $request->logo();

        if ($bestand === null) {
            return null;
        }

        return $logo->bewaar(
            $bestand,
            Project::SCHIJF,
            Project::MAP,
            $request->uitsnede(),
            Project::BEELD_MAAT,
        );
    }

    /**
     * Wat er bij een wijziging met `image_path` moet gebeuren.
     *
     * Drie gevallen, en het middelste is het belangrijkste: **er kwam
     * niets mee en er is niets gevraagd, dus we laten het staan.** Zou
     * dit veld er altijd in zitten, dan raakt de eigenaar zijn beeld
     * kwijt zodra hij een woord in zijn samenvatting verbetert.
     *
     * @return array<string, string|null>
     */
    private function beeldVeld(ProjectRequest $request, Logo $logo): array
    {
        $nieuw = $this->bewaarBeeld($request, $logo);

        if ($nieuw !== null) {
            return ['image_path' => $nieuw];
        }

        return $request->wilLogoWeg() ? ['image_path' => null] : [];
    }
}
