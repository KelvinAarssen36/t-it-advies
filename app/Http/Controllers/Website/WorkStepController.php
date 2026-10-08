<?php

namespace App\Http\Controllers\Website;

use App\Enums\PageSectionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Http\Requests\Website\WorkStepRequest;
use App\Models\WorkStep;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het onderdeel "Werkwijze": hoe een opdracht bij hem verloopt.
 *
 * Volgt het patroon van de diensten -- een lijst met een eigen volgorde en
 * een beheerbare kop erboven -- met één ding dat hier zwaarder weegt dan
 * elders: **de volgorde ís de inhoud.** Bij de diensten bepaalt slepen
 * alleen wat er eerst staat; hier bepaalt het ook het nummer op de kaart
 * en daarmee de betekenis van de stap. Vandaar dat het sleepvenster hier
 * al vanaf twee stappen verschijnt en het scherm erbij zegt wat slepen
 * doet.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 */
class WorkStepController extends Controller
{
    use BewaartKoptekst;

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::Werkwijze;
    }

    public function index(Vertaler $vertaler): Response
    {
        $stappen = WorkStep::query()->opVolgorde()->get();

        return Inertia::render('website/Werkwijze', [
            'items' => $stappen
                ->map(fn (WorkStep $stap) => $stap->voorHetScherm())
                ->all(),

            'leeg' => $stappen->isEmpty(),

            'kop' => $this->koptekst()->voorHetScherm(),

            'opties' => [
                // Hoeveel stappen er hoogstens zijn; het scherm schakelt
                // de knop daarop uit. De grendel zelf staat in store().
                'maximum' => WorkStep::MAXIMUM,

                // Dezelfde grenzen als de validatie, zodat het scherm geen
                // teller kan tonen die de server niet kent.
                'samenvattingMax' => WorkStepRequest::SAMENVATTING_MAX,
                'resultaatMax' => WorkStepRequest::RESULTAAT_MAX,
                'verhaalMax' => WorkStepRequest::VERHAAL_MAX,
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    public function store(WorkStepRequest $request): RedirectResponse
    {
        /*
         * De grens staat hier en niet in het formulier, want hij gaat niet
         * over een veld maar over de lijst. Een foutmelding onder een
         * invoerveld zou wijzen naar iets dat niet verkeerd is ingevuld.
         *
         * En hij staat op de server en niet alleen in het scherm: de knop
         * is daar uitgeschakeld, maar een uitgeschakelde knop is geen
         * grendel.
         */
        if (WorkStep::query()->count() >= WorkStep::MAXIMUM) {
            Toast::fout(
                __('Je hebt al :aantal stappen; meer passen er niet in een werkwijze.', [
                    'aantal' => WorkStep::MAXIMUM,
                ]),
                (string) __('Wil je er toch een bij, voeg dan eerst twee bestaande stappen samen.'),
            );

            return back();
        }

        $stap = WorkStep::query()->create([
            ...$request->gegevens(),

            // Achteraan in de rij. Waar het komt te staan bepaalt de klant
            // daarna met slepen -- en daarmee ook welk nummer het krijgt.
            'position' => (int) WorkStep::query()->max('position') + 1,

            'machine_translated_at' => $request->automatischVertaald()
                ? Carbon::now()
                : null,
        ]);

        Toast::aangemaakt(
            __('De stap is toegevoegd.'),
            $stap->published
                ? __('Hij staat achteraan in je werkwijze; sleep hem op zijn plek.')
                : __('Hij staat nog niet op je website; zet hem online wanneer je wilt.'),
        );

        return back();
    }

    public function update(
        WorkStepRequest $request,
        WorkStep $workStep,
    ): RedirectResponse {
        $workStep->update([
            ...$request->gegevens(),

            /*
             * Het merkje blijft staan zolang de eigenaar de vertaalknop
             * blijft gebruiken, en verdwijnt zodra hij het Engels zelf
             * heeft nagelopen. De eerste datum blijft bewaard: dat is het
             * moment waarop er voor het laatst een machine aan te pas
             * kwam.
             */
            'machine_translated_at' => $request->automatischVertaald()
                ? ($workStep->machine_translated_at ?? Carbon::now())
                : null,
        ]);

        if (! $workStep->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De stap is aangepast.'));

        return back();
    }

    public function destroy(WorkStep $workStep): RedirectResponse
    {
        // De titel vóór het verwijderen ophalen; daarna is hij weg.
        $titel = $workStep->title_nl;

        $workStep->delete();

        Toast::verwijderd(
            __('":naam" is verwijderd.', ['naam' => $titel]),
            // De nummers schuiven op, en dat is precies wat je wil -- maar
            // het is wel iets anders dan bij een dienst weghalen.
            (string) __('De stappen erna schuiven een nummer op.'),
        );

        return back();
    }

    public function online(Request $request, WorkStep $workStep): RedirectResponse
    {
        $aan = $request->boolean('published');

        $workStep->update(['published' => $aan]);

        Toast::bijgewerkt(
            $aan
                ? __('":naam" staat nu op je website.', ['naam' => $workStep->title_nl])
                : __('":naam" staat niet meer op je website.', ['naam' => $workStep->title_nl]),
            // Hetzelfde verhaal als bij verwijderen: de rest schuift op.
            $aan ? null : (string) __('De stappen erna schuiven een nummer op.'),
        );

        return back();
    }

    /**
     * De volgorde, en daarmee de nummering.
     *
     * Dezelfde opzet als bij de projecten: de hele lijst moet meekomen,
     * niet een deel ervan. Zou een verzoek met drie van de zes ids
     * binnenkomen, dan krijgen die drie positie 1, 2 en 3 en botsen ze met
     * de andere drie.
     */
    public function volgorde(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'stappen' => ['required', 'array'],
            'stappen.*.id' => ['required', 'integer', 'exists:work_steps,id'],
        ]);

        /** @var array<int, array{id: int}> $rij */
        $rij = $gegevens['stappen'];

        $bestaand = WorkStep::query()->orderBy('id')->pluck('id')->all();
        $gekregen = array_map(fn (array $regel) => $regel['id'], $rij);
        sort($gekregen);

        if ($gekregen !== $bestaand) {
            Toast::fout(__('De lijst klopt niet meer. Ververs de pagina en probeer het opnieuw.'));

            return back();
        }

        $veranderd = DB::transaction(function () use ($rij): bool {
            $veranderd = false;

            foreach ($rij as $index => $regel) {
                $stap = WorkStep::query()->findOrFail($regel['id']);
                $stap->position = $index + 1;

                if ($stap->isDirty()) {
                    $veranderd = true;
                    $stap->save();
                }
            }

            return $veranderd;
        });

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(
            __('De volgorde van je werkwijze is aangepast.'),
            (string) __('De nummers lopen mee met de nieuwe volgorde.'),
        );

        return back();
    }
}
