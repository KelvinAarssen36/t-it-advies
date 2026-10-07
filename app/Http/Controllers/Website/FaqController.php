<?php

namespace App\Http\Controllers\Website;

use App\Enums\PageSectionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Http\Requests\Website\FaqItemRequest;
use App\Models\FaqItem;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De veelgestelde vragen: bekijken, aanmaken, wijzigen, verwijderen en
 * ordenen.
 *
 * Qua opzet de eenvoudigste module van allemaal: één lijst, geen groepen,
 * geen bijlagen, geen tweede tabel. Daarom staat er hier ook bijna niets
 * dat uitleg nodig heeft -- alles is het vaste patroon van dit project, en
 * dat is precies de bedoeling.
 *
 * Eén ding wijkt af van de statistieken ernaast: `volgorde()` doet hier
 * alléén de volgorde. Er zijn geen groepen om naartoe te verhuizen, dus
 * het venster stuurt niets anders dan een rij ids mee.
 *
 * Zie docs/architecture/modules/faq.md.
 */
class FaqController extends Controller
{
    use BewaartKoptekst;

    public function index(Vertaler $vertaler): Response
    {
        $vragen = FaqItem::query()->opVolgorde()->get();

        return Inertia::render('website/Faq', [
            'items' => $vragen
                ->map(fn (FaqItem $vraag) => $this->rij($vraag))
                ->all(),

            /*
             * Of het onderdeel leeg is. Dat is hier hetzelfde als "er
             * zijn geen vragen", maar het scherm leest prettiger met een
             * vlag dan met een telling -- en zo staat het ook bij de
             * andere modules.
             */
            'leeg' => $vragen->isEmpty(),

            'kop' => $this->koptekst()->voorHetScherm(),

            'opties' => [
                'antwoordMax' => FaqItemRequest::ANTWOORD_MAX,
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    public function store(FaqItemRequest $request): RedirectResponse
    {
        $vraag = FaqItem::query()->create([
            ...$request->gegevens(),

            // Achteraan in de rij. Waar hij komt te staan bepaalt de
            // eigenaar daarna met slepen.
            'position' => (int) FaqItem::query()->max('position') + 1,

            'machine_translated_at' => $request->automatischVertaald()
                ? Carbon::now()
                : null,
        ]);

        Toast::aangemaakt(
            __('De vraag is toegevoegd.'),
            $vraag->published
                ? __('Hij staat nu op je website.')
                : __('Hij staat nog niet op je website; zet hem online wanneer je wilt.'),
        );

        return back();
    }

    public function update(FaqItemRequest $request, FaqItem $faqItem): RedirectResponse
    {
        $faqItem->update([
            ...$request->gegevens(),

            /*
             * Het merkje "automatisch vertaald" hangt aan de tekst en
             * niet aan deze opslag: het zegt dat het Engels dat er nú
             * staat van de vertaaldienst komt en nog door niemand is
             * nagelezen.
             */
            'machine_translated_at' => $request->automatischVertaald()
                ? ($faqItem->machine_translated_at ?? Carbon::now())
                : null,
        ]);

        if (! $faqItem->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De vraag is aangepast.'));

        return back();
    }

    public function destroy(FaqItem $faqItem): RedirectResponse
    {
        // De vraag vóór het verwijderen ophalen; daarna is hij weg.
        $vraag = $faqItem->question_nl;

        $faqItem->delete();

        Toast::verwijderd(__('":naam" is verwijderd.', ['naam' => $vraag]));

        return back();
    }

    /**
     * Het schuifje online/offline.
     *
     * Een eigen route, omdat één waarde omzetten niet het hele formulier
     * langs de validatie hoort te sturen.
     */
    public function online(Request $request, FaqItem $faqItem): RedirectResponse
    {
        $aan = $request->boolean('published');

        $faqItem->update(['published' => $aan]);

        Toast::bijgewerkt($aan
            ? __('":naam" staat nu op je website.', ['naam' => $faqItem->question_nl])
            : __('":naam" staat niet meer op je website.', ['naam' => $faqItem->question_nl]));

        return back();
    }

    /**
     * De volgorde van de vragen.
     *
     * Opnieuw nummeren vanaf 1 en niet verschuiven, hetzelfde patroon als
     * bij de diensten, de certificaten en de statistieken: dan kan er
     * geen gat of dubbele positie ontstaan, hoe vaak je ook sleept.
     */
    public function volgorde(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'vragen' => ['required', 'array'],
            'vragen.*.id' => ['required', 'integer', 'exists:faq_items,id'],
        ]);

        /** @var array<int, array{id: int}> $rij */
        $rij = $gegevens['vragen'];

        /*
         * De hele lijst moet meekomen, niet een deel ervan. Zou een
         * verzoek met drie van de zes ids binnenkomen, dan krijgen die
         * drie positie 1, 2 en 3 en botsen ze met de andere drie.
         */
        $bestaand = FaqItem::query()->orderBy('id')->pluck('id')->all();
        $gekregen = array_map(fn (array $regel) => $regel['id'], $rij);
        sort($gekregen);

        if ($gekregen !== $bestaand) {
            Toast::fout(__('De lijst klopt niet meer. Ververs de pagina en probeer het opnieuw.'));

            return back();
        }

        $veranderd = DB::transaction(function () use ($rij): bool {
            $veranderd = false;

            foreach ($rij as $index => $regel) {
                $vraag = FaqItem::query()->findOrFail($regel['id']);

                $vraag->position = $index + 1;

                if ($vraag->isDirty()) {
                    $veranderd = true;
                    $vraag->save();
                }
            }

            return $veranderd;
        });

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De volgorde van je vragen is aangepast.'));

        return back();
    }

    /** De kop boven het hele blok. */
    public function kop(Request $request): RedirectResponse
    {
        if (! $this->verwerkKoptekst($request)) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De kop boven je vragen is aangepast.'));

        return back();
    }

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::Faq;
    }

    /**
     * Eén vraag, zoals het beheerscherm hem nodig heeft.
     *
     * Allebei de talen los, want dit scherm bewerkt ze allebei. De
     * publieke site krijgt een andere vorm: daar is de keuze tussen
     * Nederlands en Engels al gemaakt. Zie FaqItem::voorDeSite().
     *
     * @return array<string, mixed>
     */
    private function rij(FaqItem $vraag): array
    {
        return [
            'id' => $vraag->id,

            // SortableList werkt met tekstsleutels; zie dat component.
            'key' => (string) $vraag->id,

            'published' => $vraag->published,
            'question_nl' => $vraag->question_nl,
            'question_en' => $vraag->question_en,
            'answer_nl' => $vraag->answer_nl,
            'answer_en' => $vraag->answer_en,
            'automatisch_vertaald' => $vraag->machine_translated_at !== null,
        ];
    }
}
