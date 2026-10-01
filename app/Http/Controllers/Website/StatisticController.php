<?php

namespace App\Http\Controllers\Website;

use App\Enums\PageSectionKey;
use App\Enums\StatisticDisplay;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Http\Requests\Website\StatisticRequest;
use App\Models\Statistic;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De statistieken: bekijken, aanmaken, wijzigen, verwijderen en ordenen.
 *
 * De vijfde module, en qua opzet de eenvoudigste: één lijst, geen
 * bijlagen, geen tweede tabel. Wat hem bijzonder maakt zit aan de
 * kant van de bezoeker -- zie StatistiekenSection.vue.
 *
 * Twee dingen die hier anders liggen dan bij de diensten:
 *
 * **`volgorde()` doet de indeling en niet alleen de volgorde.** Per
 * cijfer komt er een positie, een groep en de Engelse naam van die groep
 * mee. Dat komt van het indelingsvenster, waar elke groep een vak is:
 * slepen naar een ander vak is verhuizen, en de twee naamvelden van dat
 * vak gelden voor alles wat erin ligt.
 *
 * **Er gaat een lijst met bestaande groepen mee naar het scherm.** Het
 * groepsveld in het bewerkvenster is vrije tekst, en zonder suggesties
 * belanden "netwerk" en "Netwerk" naast elkaar als twee groepen.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
class StatisticController extends Controller
{
    use BewaartKoptekst;

    public function index(Vertaler $vertaler): Response
    {
        $statistieken = Statistic::query()->opVolgorde()->get();

        return Inertia::render('website/Statistieken', [
            'items' => $statistieken
                ->map(fn (Statistic $statistiek) => $this->rij($statistiek))
                ->all(),

            /*
             * Of het onderdeel leeg is. Dat is hier hetzelfde als "er
             * zijn geen statistieken", maar het scherm leest prettiger
             * met een vlag dan met een telling -- en zo staat het ook
             * bij de andere modules.
             */
            'leeg' => $statistieken->isEmpty(),

            'kop' => $this->koptekst()->voorHetScherm(),

            'opties' => [
                'weergave' => StatisticDisplay::opties(),
                'groepen' => Statistic::bestaandeGroepen(),
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    public function store(StatisticRequest $request): RedirectResponse
    {
        $statistiek = Statistic::query()->create([
            ...$request->gegevens(),

            // Achteraan in de rij. Waar hij komt te staan bepaalt de
            // klant daarna met slepen.
            'position' => (int) Statistic::query()->max('position') + 1,

            'machine_translated_at' => $request->automatischVertaald()
                ? Carbon::now()
                : null,
        ]);

        Toast::aangemaakt(
            __('De statistiek is toegevoegd.'),
            $statistiek->published
                ? __('Hij staat nu op je website.')
                : __('Hij staat nog niet op je website; zet hem online wanneer je wilt.'),
        );

        return back();
    }

    public function update(StatisticRequest $request, Statistic $statistic): RedirectResponse
    {
        $statistic->update([
            ...$request->gegevens(),

            /*
             * Het merkje "automatisch vertaald" hangt aan de tekst en
             * niet aan deze opslag: het zegt dat het Engels dat er nú
             * staat van de dienst komt en nog door niemand is nagelezen.
             */
            'machine_translated_at' => $request->automatischVertaald()
                ? ($statistic->machine_translated_at ?? Carbon::now())
                : null,
        ]);

        if (! $statistic->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De statistiek is aangepast.'));

        return back();
    }

    public function destroy(Statistic $statistic): RedirectResponse
    {
        // De naam vóór het verwijderen ophalen; daarna is hij weg.
        $naam = $statistic->label_nl;

        $statistic->delete();

        Toast::verwijderd(__('":naam" is verwijderd.', ['naam' => $naam]));

        return back();
    }

    /**
     * Het schuifje online/offline.
     *
     * Een eigen route, omdat één waarde omzetten niet het hele formulier
     * langs de validatie hoort te sturen.
     */
    public function online(Request $request, Statistic $statistic): RedirectResponse
    {
        $aan = $request->boolean('published');

        $statistic->update(['published' => $aan]);

        Toast::bijgewerkt($aan
            ? __('":naam" staat nu op je website.', ['naam' => $statistic->label_nl])
            : __('":naam" staat niet meer op je website.', ['naam' => $statistic->label_nl]));

        return back();
    }

    /**
     * De volgorde van de statistieken.
     *
     * Opnieuw nummeren vanaf 1 en niet verschuiven, hetzelfde patroon
     * als bij de diensten en de certificaten: dan kan er geen gat of
     * dubbele positie ontstaan, hoe vaak je ook sleept.
     */
    public function volgorde(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'statistieken' => ['required', 'array'],
            'statistieken.*.id' => ['required', 'integer', 'exists:statistics,id'],

            /*
             * De groep gaat mee, want dit venster doet meer dan de
             * volgorde: elk vak in dat venster is een groep, en een
             * statistiek naar een ander vak slepen is verhuizen. Zie
             * StatistiekIndelingDialoog voor waarom dat daar hoort.
             *
             * `present` en niet `required`: een lege groep is een
             * geldige waarde en betekent "los bovenaan".
             */
            'statistieken.*.groep' => ['present', 'nullable', 'string', 'max:60'],

            /*
             * De Engelse naam van die groep, als het venster hem
             * meestuurt.
             *
             * `sometimes`, want een verzoek met alleen de volgorde en de
             * groep moet blijven werken -- dan zoekt `engelseGroep()`
             * hem er zelf bij. Stuurt het venster hem wél mee, dan is
             * dat de waarheid: daar is de groep een vak met twee
             * naamvelden, en krijgt elk cijfer in dat vak diezelfde twee
             * namen. Een groep kan daarmee geen twee Engelse koppen meer
             * hebben.
             */
            'statistieken.*.groep_en' => ['sometimes', 'nullable', 'string', 'max:60'],
        ]);

        /** @var array<int, array{id: int, groep: string|null, groep_en?: string|null}> $rij */
        $rij = $gegevens['statistieken'];

        /*
         * De hele lijst moet meekomen, niet een deel ervan. Zou een
         * verzoek met drie van de zes ids binnenkomen, dan krijgen die
         * drie positie 1, 2 en 3 en botsen ze met de andere drie.
         */
        $bestaand = Statistic::query()->orderBy('id')->pluck('id')->all();
        $gekregen = array_map(fn (array $regel) => $regel['id'], $rij);
        sort($gekregen);

        if ($gekregen !== $bestaand) {
            Toast::fout(__('De lijst klopt niet meer. Ververs de pagina en probeer het opnieuw.'));

            return back();
        }

        $veranderd = DB::transaction(function () use ($rij) {
            $veranderd = false;

            foreach ($rij as $index => $regel) {
                $statistiek = Statistic::query()->findOrFail($regel['id']);

                $statistiek->position = $index + 1;
                $statistiek->group_nl = blank($regel['groep'])
                    ? null
                    : trim($regel['groep']);

                /*
                 * De Engelse naam van de groep.
                 *
                 * Stuurt het venster hem mee, dan is dat de waarheid:
                 * daar is de groep een vak met een Nederlands en een
                 * Engels naamveld, en hoort elk cijfer in dat vak
                 * dezelfde twee namen te krijgen. Dat is ook wat een
                 * groep repareert waarvan het ene cijfer een Engelse
                 * naam had en het andere niet.
                 *
                 * Komt hij niet mee en verhuist de statistiek, dan
                 * neemt hij de Engelse naam van zijn nieuwe groep over
                 * -- en niet die van de groep die hij net verliet. Dat
                 * laatste zou een Engelse bezoeker een kopje geven dat
                 * een Nederlandse bezoeker niet ziet. Hem alleen
                 * leegmaken is ook niet goed: het kopje van een groep
                 * komt van zijn eerste item, dus dan verliest de hele
                 * groep zijn Engelse naam zodra de verhuizer vooraan
                 * komt te staan.
                 */
                if (array_key_exists('groep_en', $regel)) {
                    $statistiek->group_en = blank($regel['groep_en'])
                        ? null
                        : trim((string) $regel['groep_en']);
                } elseif ($statistiek->isDirty('group_nl')) {
                    $statistiek->group_en = $this->engelseGroep(
                        $statistiek->group_nl,
                        $statistiek->id,
                    );
                }

                /*
                 * Geen groep betekent ook geen Engelse groepsnaam. Zou
                 * die blijven staan, dan draagt een los cijfer een kopje
                 * met zich mee dat nergens te zien is -- tot het moment
                 * dat het weer in een groep belandt en ineens de
                 * verkeerde Engelse kop oplevert.
                 */
                if ($statistiek->group_nl === null) {
                    $statistiek->group_en = null;
                }

                if ($statistiek->isDirty()) {
                    $veranderd = true;
                }

                $statistiek->save();
            }

            return $veranderd;
        });

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De indeling van je statistieken is aangepast.'));

        return back();
    }

    /** De kop boven het hele blok. */
    public function kop(Request $request): RedirectResponse
    {
        if (! $this->verwerkKoptekst($request)) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De kop boven je statistieken is aangepast.'));

        return back();
    }

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::Statistieken;
    }

    /**
     * De Engelse naam die deze groep al gebruikt, als die er is.
     *
     * Wordt gevraagd zodra een statistiek naar een andere groep
     * verhuist. `$behalve` is zijn eigen id: die rij staat op dat moment
     * al met de nieuwe groep in het geheugen maar nog niet opgeslagen,
     * en zichzelf als bron gebruiken levert de oude waarde op.
     */
    private function engelseGroep(?string $groep, int $behalve): ?string
    {
        if (blank($groep)) {
            return null;
        }

        $naam = Statistic::query()
            ->where('group_nl', $groep)
            ->whereKeyNot($behalve)
            ->whereNotNull('group_en')
            ->value('group_en');

        return blank($naam) ? null : (string) $naam;
    }

    /**
     * Eén statistiek, zoals het beheerscherm hem nodig heeft.
     *
     * Allebei de talen los, want dit scherm bewerkt ze allebei. De
     * publieke site krijgt een andere vorm: daar is de keuze tussen
     * Nederlands en Engels al gemaakt. Zie HomeController.
     *
     * @return array<string, mixed>
     */
    private function rij(Statistic $statistiek): array
    {
        return [
            'id' => $statistiek->id,

            // SortableList werkt met tekstsleutels; zie dat component.
            'key' => (string) $statistiek->id,

            'display' => $statistiek->display->value,
            'display_label' => $statistiek->display->label(),
            'published' => $statistiek->published,
            'label_nl' => $statistiek->label_nl,
            'label_en' => $statistiek->label_en,
            'value' => $statistiek->value,
            'prefix' => $statistiek->prefix,
            'suffix' => $statistiek->suffix,
            'note_nl' => $statistiek->note_nl,
            'note_en' => $statistiek->note_en,
            'group_nl' => $statistiek->group_nl,
            'group_en' => $statistiek->group_en,
            'automatisch_vertaald' => $statistiek->machine_translated_at !== null,
        ];
    }
}
