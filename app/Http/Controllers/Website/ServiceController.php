<?php

namespace App\Http\Controllers\Website;

use App\Enums\PageSectionKey;
use App\Enums\ServiceIcon;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Http\Requests\Website\ServiceRequest;
use App\Models\Service;
use App\Models\ServicePoint;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De diensten: bekijken, aanmaken, wijzigen, verwijderen en ordenen.
 *
 * De derde module, en hij combineert de twee die er al waren. Van
 * [Ervaring](ExperienceController.php) komt de lijst met een venster per
 * item; van [Kop](HeroController.php) het beheren van vaste tekst boven
 * een blok.
 *
 * Een paar keuzes die hier anders liggen dan bij de tijdlijn:
 *
 * **Geen zoekveld en geen paginering.** Een loopbaan telt tientallen
 * functies; diensten zijn er een handvol. Een zoekveld boven vier regels
 * is gereedschap dat in de weg staat.
 *
 * **Geen detailpagina.** Alles van een dienst past in één venster. Bij
 * een ervaring is die pagina er om na te kijken -- twee talen naast
 * elkaar, de datums, het logo -- en dat weegt hier niet op tegen een
 * klik extra.
 *
 * **Wél een eigen volgorde.** Een tijdlijn ordent zichzelf op datum;
 * welke dienst je het eerst noemt is een redactionele keuze. Die zet de
 * klant met slepen, in een eigen venster.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
class ServiceController extends Controller
{
    use BewaartKoptekst;

    public function index(Vertaler $vertaler): Response
    {
        $diensten = Service::query()
            ->with('points')
            ->opVolgorde()
            ->get();

        return Inertia::render('website/Diensten', [
            'items' => $diensten->map(fn (Service $dienst) => $this->rij($dienst))->all(),

            /*
             * Of het onderdeel leeg is. Dat is hier hetzelfde als "er
             * zijn geen diensten", want er wordt niet gezocht -- maar
             * het scherm leest prettiger met een vlag dan met een
             * telling, en zo staat het ook bij de tijdlijn.
             */
            'leeg' => $diensten->isEmpty(),

            'kop' => $this->koptekst()->voorHetScherm(),

            'opties' => [
                'icon' => ServiceIcon::opties(),
                'puntenMaximum' => Service::PUNTEN_MAXIMUM,
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    public function store(ServiceRequest $request): RedirectResponse
    {
        $dienst = DB::transaction(function () use ($request) {
            $dienst = Service::query()->create([
                ...$request->gegevens(),

                // Achteraan in de rij. Waar hij komt te staan bepaalt de
                // klant daarna met slepen; hem ergens tussen zetten zou
                // een keuze zijn die wij voor hem maken.
                'position' => (int) Service::query()->max('position') + 1,

                'machine_translated_at' => $request->automatischVertaald()
                    ? Carbon::now()
                    : null,
            ]);

            $this->bewaarPunten($dienst, $request->punten());

            return $dienst;
        });

        Toast::aangemaakt(
            __('De dienst is toegevoegd.'),
            $dienst->published
                ? __('Hij staat nu op je website.')
                : __('Hij staat nog niet op je website; zet hem online wanneer je wilt.'),
        );

        return back();
    }

    public function update(ServiceRequest $request, Service $service): RedirectResponse
    {
        $veranderd = DB::transaction(function () use ($request, $service) {
            $service->fill($request->gegevens());

            /*
             * Het merkje "automatisch vertaald" hangt aan de tekst en
             * niet aan deze opslag: het zegt dat het Engels dat er nú
             * staat van de dienst komt en nog door niemand is nagelezen.
             * Het scherm bepaalt of dat nog waar is -- het weet als
             * enige of de klant daarna nog in de Engelse velden heeft
             * getypt.
             */
            $service->machine_translated_at = $request->automatischVertaald()
                ? ($service->machine_translated_at ?? Carbon::now())
                : null;

            // Vóór het opslaan vragen, want daarna is niets meer "dirty".
            $veranderd = $service->isDirty();

            $service->save();

            return $this->bewaarPunten($service, $request->punten()) || $veranderd;
        });

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De dienst is aangepast.'));

        return back();
    }

    public function destroy(Service $service): RedirectResponse
    {
        // De naam vóór het verwijderen ophalen; daarna is hij weg.
        $naam = $service->activityLabel();

        // De punten gaan mee door de sleutel in de migratie.
        $service->delete();

        Toast::verwijderd(__('":naam" is verwijderd.', ['naam' => $naam]));

        return back();
    }

    /**
     * Het schuifje online/offline.
     *
     * Een eigen route, omdat één waarde omzetten niet het hele formulier
     * langs de validatie hoort te sturen.
     */
    public function online(Request $request, Service $service): RedirectResponse
    {
        $aan = $request->boolean('published');

        $service->update(['published' => $aan]);

        Toast::bijgewerkt($aan
            ? __('":naam" staat nu op je website.', ['naam' => $service->activityLabel()])
            : __('":naam" staat niet meer op je website.', ['naam' => $service->activityLabel()]));

        return back();
    }

    /**
     * De volgorde van de diensten.
     *
     * Opnieuw nummeren vanaf 1 en niet verschuiven, hetzelfde patroon
     * als bij de indeling van de pagina: dan kan er geen gat of
     * dubbele positie ontstaan, hoe vaak je ook sleept. Zie
     * LayoutController::bewaar().
     */
    public function volgorde(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'diensten' => ['required', 'array'],
            'diensten.*' => ['required', 'integer', 'exists:services,id'],
        ]);

        /** @var array<int, int> $rij */
        $rij = $gegevens['diensten'];

        /*
         * De hele lijst moet meekomen, niet een deel ervan. Zou een
         * verzoek met drie van de zes ids binnenkomen, dan krijgen die
         * drie positie 1, 2 en 3 en botsen ze met de andere drie.
         */
        $bestaand = Service::query()->orderBy('id')->pluck('id')->all();
        $gekregen = $rij;
        sort($gekregen);

        if ($gekregen !== $bestaand) {
            Toast::fout(__('De lijst klopt niet meer. Ververs de pagina en probeer het opnieuw.'));

            return back();
        }

        $veranderd = DB::transaction(function () use ($rij) {
            $veranderd = false;

            foreach ($rij as $index => $id) {
                $dienst = Service::query()->findOrFail($id);
                $dienst->position = $index + 1;

                if ($dienst->isDirty()) {
                    $veranderd = true;
                }

                $dienst->save();
            }

            return $veranderd;
        });

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De volgorde van je diensten is aangepast.'));

        return back();
    }

    /**
     * De kop boven het hele blok.
     *
     * Eén opslag voor de drie teksten, want op de website is het één
     * blok. Zelfde opzet als HeroController::update().
     */
    public function kop(Request $request): RedirectResponse
    {
        if (! $this->verwerkKoptekst($request)) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De kop boven je diensten is aangepast.'));

        return back();
    }

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::Diensten;
    }

    /**
     * De punten van een dienst gelijktrekken met wat er binnenkwam.
     *
     * Bijwerken wat er al was, aanvullen wat erbij komt, weghalen wat er
     * niet meer bij zit. Alles weggooien en opnieuw aanmaken zou
     * makkelijker zijn, maar dan krijgt elk punt bij elke opslag een
     * nieuw id -- en verandert er dus altijd iets.
     *
     * @param  array<int, array{text_nl: string, text_en: string|null}>  $punten
     * @return bool Of er echt iets veranderde.
     */
    private function bewaarPunten(Service $dienst, array $punten): bool
    {
        $bestaand = $dienst->points()->get()->values();
        $veranderd = false;

        foreach ($punten as $index => $punt) {
            /** @var ServicePoint $rij */
            $rij = $bestaand->get($index) ?? new ServicePoint(['service_id' => $dienst->id]);

            $rij->fill([
                'service_id' => $dienst->id,
                'position' => $index + 1,
                'text_nl' => $punt['text_nl'],
                'text_en' => $punt['text_en'],
            ]);

            if ($rij->isDirty()) {
                $veranderd = true;
            }

            $rij->save();
        }

        // Wat er over is stond er wel en hoort er niet meer te staan.
        foreach ($bestaand->slice(count($punten)) as $overtollig) {
            $overtollig->delete();
            $veranderd = true;
        }

        return $veranderd;
    }

    /**
     * Eén dienst, zoals het beheerscherm hem nodig heeft.
     *
     * Allebei de talen los, want dit is een bewerkscherm en geen
     * weergave: de klant vult ze allebei zelf in en moet dus zien wat er
     * in allebei staat.
     *
     * @return array<string, mixed>
     */
    private function rij(Service $dienst): array
    {
        return [
            'id' => $dienst->id,
            'key' => (string) $dienst->id,
            'icon' => $dienst->icon->value,
            'icon_label' => $dienst->icon->label(),
            'published' => $dienst->published,
            'title_nl' => $dienst->title_nl,
            'title_en' => $dienst->title_en,
            'summary_nl' => $dienst->summary_nl,
            'summary_en' => $dienst->summary_en,
            'body_nl' => $dienst->body_nl,
            'body_en' => $dienst->body_en,

            'punten' => $dienst->points->map(fn (ServicePoint $punt) => [
                'text_nl' => $punt->text_nl,
                'text_en' => $punt->text_en,
            ])->all(),

            /*
             * Of het Engels compleet is. Op de server berekend en niet in
             * Vue, want de regel welke velden meetellen -- alleen die in
             * het Nederlands gevuld zijn -- hoort bij het model en niet
             * bij het scherm.
             */
            'vertaald' => $this->vertaald($dienst),
            'automatisch_vertaald' => $dienst->machine_translated_at !== null,
        ];
    }

    /**
     * Is alles wat in het Nederlands staat ook in het Engels ingevuld?
     *
     * Een leeg Nederlands veld telt niet mee: daar valt niets te
     * vertalen, dus dat maakt een dienst niet onvertaald.
     */
    private function vertaald(Service $dienst): bool
    {
        $paren = [
            [$dienst->title_nl, $dienst->title_en],
            [$dienst->summary_nl, $dienst->summary_en],
            [$dienst->body_nl, $dienst->body_en],
        ];

        foreach ($dienst->points as $punt) {
            $paren[] = [$punt->text_nl, $punt->text_en];
        }

        foreach ($paren as [$nl, $en]) {
            if (filled($nl) && blank($en)) {
                return false;
            }
        }

        return true;
    }
}
