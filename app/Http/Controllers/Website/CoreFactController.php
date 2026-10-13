<?php

namespace App\Http\Controllers\Website;

use App\Enums\CoreFactIcon;
use App\Enums\PageSectionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Http\Requests\Website\CoreFactRequest;
use App\Models\CoreFact;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De kerngegevens: bekijken, aanmaken, wijzigen, verwijderen en ordenen.
 *
 * Volgt `FaqController` regel voor regel; dat is de eenvoudigste module van
 * het project en heeft precies dezelfde zeven acties. Wat hier anders is:
 *
 * - **Er is een maximum.** Acht, en dat wordt in `store()` afgevangen in
 *   plaats van in de FormRequest: het gaat niet over de geldigheid van
 *   wat er is ingevuld maar over hoeveel er al staan.
 * - **De soorten gaan mee naar het scherm.** Niet alleen als keuzelijst,
 *   maar met een voorbeeldzin erbij -- het lege scherm gebruikt die om
 *   uit te leggen wat een kerngegeven is.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */
class CoreFactController extends Controller
{
    use BewaartKoptekst;

    public function index(Vertaler $vertaler): Response
    {
        $gegevens = CoreFact::query()->opVolgorde()->get();

        return Inertia::render('website/Kerngegevens', [
            'items' => $gegevens
                ->map(fn (CoreFact $gegeven) => $gegeven->voorHetScherm())
                ->all(),

            'leeg' => $gegevens->isEmpty(),

            'kop' => $this->koptekst()->voorHetScherm(),

            'opties' => [
                'soorten' => CoreFactIcon::opties(),
                'waardeMax' => CoreFactRequest::WAARDE_MAX,
                'notitieMax' => CoreFactRequest::NOTITIE_MAX,
                'maximum' => CoreFact::MAXIMUM,
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    public function store(CoreFactRequest $request): RedirectResponse
    {
        /*
         * Het maximum hier en niet in de FormRequest: dit gaat niet over
         * de geldigheid van de invoer maar over hoeveel er al staan. Een
         * foutmelding onder een veld zou daar niets over zeggen.
         */
        if (CoreFact::query()->count() >= CoreFact::MAXIMUM) {
            Toast::fout(
                __('Je hebt er al :aantal.', ['aantal' => CoreFact::MAXIMUM]),
                (string) __('Een feitenstrook met meer regels leest niemand meer. Verwijder er eerst een, of zet er een offline.'),
            );

            return back();
        }

        $gegeven = CoreFact::query()->create([
            ...$request->gegevens(),

            // Achteraan in de rij. Waar hij komt te staan bepaalt de
            // eigenaar daarna met slepen.
            'position' => (int) CoreFact::query()->max('position') + 1,

            'machine_translated_at' => $request->automatischVertaald()
                ? Carbon::now()
                : null,
        ]);

        Toast::aangemaakt(
            __('Het kerngegeven is toegevoegd.'),
            $gegeven->published
                ? __('Het staat nu op je website.')
                : __('Het staat nog niet op je website; zet het online wanneer je wilt.'),
        );

        return back();
    }

    public function update(CoreFactRequest $request, CoreFact $coreFact): RedirectResponse
    {
        $coreFact->update([
            ...$request->gegevens(),

            /*
             * Het merkje "automatisch vertaald" hangt aan de tekst en
             * niet aan deze opslag: het zegt dat het Engels dat er nú
             * staat van de vertaaldienst komt en nog door niemand is
             * nagelezen.
             */
            'machine_translated_at' => $request->automatischVertaald()
                ? ($coreFact->machine_translated_at ?? Carbon::now())
                : null,
        ]);

        if (! $coreFact->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('Het kerngegeven is aangepast.'));

        return back();
    }

    public function destroy(CoreFact $coreFact): RedirectResponse
    {
        // Het label vóór het verwijderen ophalen; daarna is het weg.
        $label = $coreFact->label_nl;

        $coreFact->delete();

        Toast::verwijderd(__('":naam" is verwijderd.', ['naam' => $label]));

        return back();
    }

    /**
     * Het schuifje online/offline.
     *
     * Een eigen route, omdat één waarde omzetten niet het hele formulier
     * langs de validatie hoort te sturen.
     */
    public function online(Request $request, CoreFact $coreFact): RedirectResponse
    {
        $aan = $request->boolean('published');

        $coreFact->update(['published' => $aan]);

        Toast::bijgewerkt($aan
            ? __('":naam" staat nu op je website.', ['naam' => $coreFact->label_nl])
            : __('":naam" staat niet meer op je website.', ['naam' => $coreFact->label_nl]));

        return back();
    }

    /**
     * De volgorde van de kerngegevens.
     *
     * Opnieuw nummeren vanaf 1 en niet verschuiven, hetzelfde patroon als
     * bij de vragen, de diensten en de statistieken: dan kan er geen gat
     * of dubbele positie ontstaan, hoe vaak je ook sleept.
     */
    public function volgorde(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:core_facts,id'],
        ]);

        /** @var array<int, array{id: int}> $rij */
        $rij = $gegevens['items'];

        /*
         * De hele lijst moet meekomen, niet een deel ervan. Zou een
         * verzoek met drie van de zes ids binnenkomen, dan krijgen die
         * drie positie 1, 2 en 3 en botsen ze met de andere drie.
         */
        $bestaand = CoreFact::query()->orderBy('id')->pluck('id')->all();
        $gekregen = array_map(fn (array $regel) => $regel['id'], $rij);
        sort($gekregen);

        if ($gekregen !== $bestaand) {
            Toast::fout(__('De lijst klopt niet meer. Ververs de pagina en probeer het opnieuw.'));

            return back();
        }

        $veranderd = DB::transaction(function () use ($rij): bool {
            $veranderd = false;

            foreach ($rij as $index => $regel) {
                $gegeven = CoreFact::query()->findOrFail($regel['id']);

                $gegeven->position = $index + 1;

                if ($gegeven->isDirty()) {
                    $veranderd = true;
                    $gegeven->save();
                }
            }

            return $veranderd;
        });

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De volgorde van je kerngegevens is aangepast.'));

        return back();
    }

    /** De kop boven het hele blok. */
    public function kop(Request $request): RedirectResponse
    {
        if (! $this->verwerkKoptekst($request)) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De kop boven je kerngegevens is aangepast.'));

        return back();
    }

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::Kerngegevens;
    }
}
