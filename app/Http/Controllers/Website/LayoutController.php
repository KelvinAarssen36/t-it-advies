<?php

namespace App\Http\Controllers\Website;

use App\Enums\PageSectionKey;
use App\Http\Controllers\Controller;
use App\Models\PageSection;
use App\Support\Page\SectionContent;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De indeling van de landingspagina.
 *
 * Dit is het overzichtsscherm van de website: alle onderdelen onder elkaar,
 * in de volgorde waarin de bezoeker ze ziet. Van hieruit gaat de eigenaar
 * naar het scherm waar hij de inhoud van zo'n onderdeel bijhoudt.
 *
 * **Wat de server hier bewaakt, en de browser dus niet.** Het scherm laat
 * de kop en de voettekst met een slotje zien, maar dat slotje is opmaak.
 * Wat er echt voor zorgt dat ze op hun plek blijven, staat in `update()`:
 * die accepteert precies de verplaatsbare onderdelen en niets anders. Een
 * verzoek dat de kop naar beneden probeert te zetten, wordt afgewezen --
 * ook als het rechtstreeks wordt verstuurd.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class LayoutController extends Controller
{
    public function index(SectionContent $inhoud): Response
    {
        return Inertia::render('website/Indeling', [
            'sections' => $this->rijen($inhoud),
            // Voor de adresbalk boven het overzicht. Uit de route en niet
            // uit het huidige verzoek: het portaal kan ooit op een eigen
            // (sub)domein komen te staan, en dan hoort er nog steeds het
            // adres van de website te staan.
            'host' => parse_url(route('home'), PHP_URL_HOST),
        ]);
    }

    /**
     * De nieuwe volgorde en zichtbaarheid opslaan.
     *
     * Volgorde en schuifjes komen samen binnen, want ze worden samen
     * bevestigd. Zie de toelichting in routes/website.php.
     */
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.key' => ['required', 'string', Rule::enum(PageSectionKey::class)],
            'sections.*.visible' => ['required', 'boolean'],
        ]);

        /** @var array<int, array{key: string, visible: bool}> $rijen */
        $rijen = array_values($data['sections']);

        $this->controleerVolledig($rijen);

        $veranderd = DB::transaction(fn () => $this->bewaar($rijen));

        /*
         * Niets veranderd is geen fout, maar ook geen reden om te doen
         * alsof er iets gebeurd is. Een melding "opgeslagen" bij een lege
         * opslag leert de eigenaar dat die melding niets betekent.
         */
        if ($veranderd === 0) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(
            __('De indeling is aangepast.'),
            __('De website toont de onderdelen nu in deze volgorde.'),
        );

        return back();
    }

    /**
     * Precies de verplaatsbare onderdelen, elk één keer.
     *
     * Dit is de grendel op de kop en de voettekst. Hij is met opzet streng
     * in beide richtingen: een onderdeel te veel betekent dat iemand iets
     * probeert dat niet kan, een onderdeel te weinig dat er eentje zou
     * blijven staan op een plek die niet meer klopt.
     *
     * @param  array<int, array{key: string, visible: bool}>  $rijen
     *
     * @throws ValidationException
     */
    private function controleerVolledig(array $rijen): void
    {
        $gekregen = array_column($rijen, 'key');
        sort($gekregen);

        $verwacht = array_map(
            fn (PageSectionKey $sectie) => $sectie->value,
            PageSectionKey::verplaatsbaar(),
        );
        sort($verwacht);

        if ($gekregen !== $verwacht) {
            throw ValidationException::withMessages([
                'sections' => __('Deze indeling klopt niet. Ververs de pagina en probeer het opnieuw.'),
            ]);
        }
    }

    /**
     * Wegschrijven, en teruggeven hoeveel rijen er echt anders werden.
     *
     * De rijen worden hernummerd als 1, 2, 3 -- niet bijgewerkt met een
     * verschuiving. Dat is het verschil tussen een volgorde die na honderd
     * sleepacties nog klopt en een die langzaam uit elkaar loopt.
     *
     * @param  array<int, array{key: string, visible: bool}>  $rijen
     */
    private function bewaar(array $rijen): int
    {
        $veranderd = 0;

        foreach ($rijen as $index => $rij) {
            $sectie = PageSection::query()->firstOrNew([
                'key' => PageSectionKey::from($rij['key']),
            ]);

            $sectie->fill([
                'position' => $index + 1,
                'visible' => $rij['visible'],
            ]);

            // isDirty() moet vóór het opslaan gelezen worden: daarna is er
            // niets meer vies en telt niets als veranderd. Dit getal bepaalt
            // welke melding de eigenaar krijgt.
            if ($sectie->isDirty()) {
                $veranderd++;
            }

            $sectie->save();
        }

        return $veranderd;
    }

    /**
     * Alle onderdelen voor het scherm: kop bovenaan, voettekst onderaan,
     * de rest ertussen op volgorde.
     *
     * De twee vaste onderdelen worden hier neergezet omdát ze vast zijn en
     * niet omdat hun positie in de database dat zegt. Dat maakt het scherm
     * ongevoelig voor een rij met een raar getal erin.
     *
     * @return array<int, array<string, mixed>>
     */
    private function rijen(SectionContent $inhoud): array
    {
        $opgeslagen = PageSection::query()
            ->opVolgorde()
            ->get()
            ->keyBy(fn (PageSection $rij) => $rij->key->value);

        $volgorde = [
            PageSectionKey::Hero,
            ...$opgeslagen
                ->map(fn (PageSection $rij) => $rij->key)
                ->filter(fn (PageSectionKey $sectie) => ! $sectie->vast())
                ->values()
                ->all(),
            PageSectionKey::Footer,
        ];

        // Is er nog niet geseed, dan staat de volgorde uit de code klaar.
        // Zonder dit is het scherm leeg op een verse installatie, en dan
        // lijkt het stuk in plaats van ongevuld.
        if (count($volgorde) === 2) {
            $volgorde = [
                PageSectionKey::Hero,
                ...PageSectionKey::verplaatsbaar(),
                PageSectionKey::Footer,
            ];
        }

        return array_map(
            fn (PageSectionKey $sectie) => $this->rij($sectie, $opgeslagen->get($sectie->value), $inhoud),
            $volgorde,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function rij(PageSectionKey $sectie, ?PageSection $rij, SectionContent $inhoud): array
    {
        // Geen rij betekent dat er nog niet geseed is; dan staat alles aan.
        $aangezet = $rij === null || $rij->visible;
        $gevuld = $inhoud->gevuld($sectie);
        $route = $sectie->beheerRoute();

        return [
            'key' => $sectie->value,
            'label' => $sectie->label(),
            'description' => $sectie->omschrijving(),
            'fixed' => $sectie->vast(),
            'visible' => $aangezet,
            'filled' => $gevuld,
            // Hoeveel items erin staan, of null als er niets te tellen valt
            // omdat de tekst in de code staat. Zie SectionContent.
            'count' => $inhoud->aantal($sectie),
            /*
             * Of hij daadwerkelijk op de website staat. Drie dingen moeten
             * kloppen, en het scherm laat per geval iets anders zien:
             * vast (altijd), aangezet, en gevuld.
             */
            'live' => $sectie->vast() || ($aangezet && $gevuld),
            'manageUrl' => $route === null ? null : route($route),

            /*
             * Of er überhaupt iets te beheren valt. Zonder dit verschil
             * zegt het scherm bij LinkedIn "Nog niet te beheren", en dat
             * is een belofte die we niet gaan waarmaken: daar is één
             * link en die ligt bewust vast. Zie PageSectionKey::teBeheren().
             */
            'manageable' => $sectie->teBeheren(),
        ];
    }
}
