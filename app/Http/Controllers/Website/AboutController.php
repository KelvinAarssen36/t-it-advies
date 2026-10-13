<?php

namespace App\Http\Controllers\Website;

use App\Enums\PageSectionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Http\Requests\Website\AboutBlokRequest;
use App\Http\Requests\Website\AboutFotoRequest;
use App\Http\Requests\Website\AboutPaginaRequest;
use App\Http\Requests\Website\AboutPointRequest;
use App\Models\AboutPoint;
use App\Models\AboutSetting;
use App\Support\Media\Logo;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het onderdeel "Over mij": het korte stuk, de aparte pagina en de punten.
 *
 * **Eén scherm, drie handelingen, en dat is een correctie.** Hier zat eerst
 * één `update()` voor allebei de blokken, omdat het één formulier was. Sinds
 * het scherm een overzicht is met bewerkvensters erachter is dat een stille
 * fout: bewaart het ene venster, dan gaan de velden van het andere als leeg
 * mee en worden ze gewist. Nu heeft elk venster zijn eigen eindpunt met zijn
 * eigen validatie, en kan een halve opslag de andere helft niet raken.
 *
 * | Handeling      | Wat het doet                                 |
 * | -------------- | -------------------------------------------- |
 * | `blok()`       | De samenvatting                              |
 * | `pagina()`     | De titel, de inleiding en het verhaal        |
 * | `foto()`       | Het portret, dat op allebei staat            |
 * | `paginaAan()`  | Het schuifje, zonder de rest te valideren    |
 *
 * De punten hebben hun eigen routes, want die zijn een lijst: aanmaken,
 * wijzigen, verwijderen, slepen.
 *
 * **De foto heeft een eigen handeling omdat hij bij geen van de twee
 * versies hoort.** Hij zat eerst bij `blok()`, want daar werd hij gekozen.
 * Maar hij staat op de voorpagina én op de aparte pagina, en dan is "hij
 * hoort bij het blok" een afspraak die je moet onthouden in plaats van iets
 * wat je ziet. Met een eigen venster en een eigen eindpunt staat hij naast
 * de twee versies in plaats van in één ervan.
 *
 * **Verder werkt hij precies als een certificaatlogo.** Zelfde gedeelde
 * `Logo`-klasse, zelfde uitsnijvenster, zelfde drie gevallen bij een
 * wijziging -- alleen op 640 pixels in plaats van 256, en met zijn eigen
 * grenzen uit `media.portret`. Zie `fotoVeld()` onderaan.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
class AboutController extends Controller
{
    use BewaartKoptekst;

    public function index(Vertaler $vertaler): Response
    {
        $instelling = AboutSetting::huidige();
        $punten = AboutPoint::query()->opVolgorde()->get();

        return Inertia::render('website/OverMij', [
            'instelling' => $instelling->voorHetScherm(),

            'punten' => $punten
                ->map(fn (AboutPoint $punt) => $this->puntRij($punt))
                ->all(),

            /*
             * Of het korte blok leeg is -- en dat gaat over de
             * samenvatting en niets anders.
             *
             * **Niet over de punten.** Die staan alleen op de aparte
             * pagina. Zou de teller op de punten afgaan, dan valt je hele
             * "Over mij" van de voorpagina tot je het derde bulletje hebt
             * getypt. Zie ook de teller in AppServiceProvider.
             */
            'leeg' => blank($instelling->summary_nl) && blank($instelling->summary_en),

            'kop' => $this->koptekst()->voorHetScherm(),

            'opties' => [
                'samenvattingMax' => AboutSetting::SAMENVATTING_MAX,
                'verhaalMax' => AboutSetting::VERHAAL_MAX,
                'puntenMax' => AboutSetting::PUNTEN_MAX,
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    /**
     * Het korte blok: de samenvatting, in beide talen.
     *
     * Eén rij, dus `firstOrNew` en geen `create`: bij de eerste opslag
     * bestaat hij nog niet.
     *
     * **Hier zit geen foto bij.** Die heeft zijn eigen venster en zijn eigen
     * handeling, omdat hij op allebei de versies staat; zie `foto()`.
     */
    public function blok(AboutBlokRequest $request): RedirectResponse
    {
        $instelling = AboutSetting::query()->firstOrNew();

        $instelling->fill([
            ...$request->gegevens(),

            /*
             * Het merkje "automatisch vertaald" hangt aan de tekst en niet
             * aan deze opslag: het zegt dat het Engels dat er nú staat van
             * de vertaaldienst komt en nog door niemand is nagelezen.
             */
            'machine_translated_at' => $request->automatischVertaald()
                ? ($instelling->machine_translated_at ?? Carbon::now())
                : null,
        ]);

        $veranderd = ! $instelling->exists || $instelling->isDirty();

        $instelling->save();

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('Het stuk op je voorpagina is aangepast.'));

        return back();
    }

    /**
     * De foto, die op allebei de versies staat.
     *
     * **Daarom een eigen handeling en niet een veld bij het blok.** Hij
     * staat op de voorpagina én op `/over-mij`; zat hij bij één van die
     * twee, dan stuurt een opslag van die tekst elke keer de fotovelden mee
     * en moet deze controller uitzoeken of er wel iets over de foto is
     * gezegd. Komt dit verzoek binnen, dan gaat het over de foto.
     *
     * De drie gevallen bij een wijziging staan in `fotoVeld()`.
     */
    public function foto(AboutFotoRequest $request, Logo $logo): RedirectResponse
    {
        $instelling = AboutSetting::query()->firstOrNew();

        $oudeFoto = $instelling->photo_path;

        $instelling->fill($this->fotoVeld($request, $logo));

        $veranderd = ! $instelling->exists || $instelling->isDirty();

        $instelling->save();

        /*
         * De oude foto pas weggooien als de nieuwe staat. Zou dat eerder
         * gebeuren en de opslag daarna mislukken, dan is het bestand weg en
         * verwijst de rij nog ernaar.
         */
        if ($oudeFoto !== null && $instelling->photo_path !== $oudeFoto) {
            $instelling->newInstance(['photo_path' => $oudeFoto], true)->verwijderFoto();
        }

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(
            $instelling->foto()['eigen']
                ? __('Je foto is aangepast.')
                : __('Je foto is weggehaald; het portret uit je kop staat er weer.'),
        );

        return back();
    }

    /**
     * De teksten van de aparte pagina.
     *
     * **Hier zit geen foto bij en geen schuifje.** Allebei hebben ze hun
     * eigen route: de foto omdat hij op beide versies staat, het schuifje
     * omdat één waarde omzetten niet het hele formulier langs de validatie
     * hoort te sturen.
     */
    public function pagina(AboutPaginaRequest $request): RedirectResponse
    {
        $instelling = AboutSetting::query()->firstOrNew();

        $instelling->fill([
            ...$request->gegevens(),

            'machine_translated_at' => $request->automatischVertaald()
                ? ($instelling->machine_translated_at ?? Carbon::now())
                : null,
        ]);

        $veranderd = ! $instelling->exists || $instelling->isDirty();

        $instelling->save();

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('Je aparte pagina is aangepast.'));

        return back();
    }

    /**
     * Het schuifje: de aparte pagina aan of uit.
     *
     * Een eigen route, omdat één waarde omzetten niet het hele formulier
     * langs de validatie hoort te sturen -- zelfde afspraak als het
     * online-schuifje bij de andere modules.
     *
     * **De melding zegt het als de pagina nog leeg is.** Aanzetten zonder
     * verhaal levert geen pagina op en geen knop op de voorpagina; dat is
     * met opzet, maar dan moet de eigenaar het wel weten. Zie
     * AboutSetting::paginaStaatKlaar().
     */
    public function paginaAan(Request $request): RedirectResponse
    {
        $aan = $request->boolean('page_enabled');

        $instelling = AboutSetting::query()->firstOrNew();
        $instelling->page_enabled = $aan;
        $instelling->save();

        if (! $aan) {
            Toast::bijgewerkt(__('Je aparte pagina staat uit.'));

            return back();
        }

        Toast::bijgewerkt(
            __('Je aparte pagina staat aan.'),
            $instelling->paginaStaatKlaar()
                ? null
                : (string) __('Er staat nog geen verhaal in, dus hij is nog niet te bezoeken. Vul hem aan en hij staat er.'),
        );

        return back();
    }

    /* --- De punten --------------------------------------------------------- */

    public function puntStore(AboutPointRequest $request): RedirectResponse
    {
        /*
         * De grens staat hier en niet in de validatieregels, want hij gaat
         * over hoeveel er al zijn en niet over wat er binnenkomt. Een
         * `max`-regel op een los veld kan dat niet weten.
         */
        if (AboutPoint::query()->count() >= AboutSetting::PUNTEN_MAX) {
            Toast::fout(__('Je hebt al :aantal punten staan. Haal er eerst een weg.', [
                'aantal' => AboutSetting::PUNTEN_MAX,
            ]));

            return back();
        }

        AboutPoint::query()->create([
            ...$request->gegevens(),

            // Achteraan in de rij; waar hij hoort bepaalt de eigenaar met
            // slepen.
            'position' => (int) AboutPoint::query()->max('position') + 1,
        ]);

        Toast::aangemaakt(__('Het punt is toegevoegd.'));

        return back();
    }

    public function puntUpdate(AboutPointRequest $request, AboutPoint $point): RedirectResponse
    {
        $point->update($request->gegevens());

        if (! $point->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('Het punt is aangepast.'));

        return back();
    }

    public function puntDestroy(AboutPoint $point): RedirectResponse
    {
        $tekst = $point->text_nl;

        $point->delete();

        Toast::verwijderd(__('":naam" is verwijderd.', ['naam' => $tekst]));

        return back();
    }

    /**
     * De volgorde van de punten.
     *
     * Opnieuw nummeren vanaf 1 en niet verschuiven, hetzelfde patroon als
     * bij de andere modules: dan kan er geen gat of dubbele positie
     * ontstaan, hoe vaak je ook sleept.
     */
    public function puntVolgorde(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'punten' => ['required', 'array'],
            'punten.*.id' => ['required', 'integer', 'exists:about_points,id'],
        ]);

        /** @var array<int, array{id: int}> $rij */
        $rij = $gegevens['punten'];

        /*
         * De hele lijst moet meekomen, niet een deel ervan. Zou een verzoek
         * met drie van de zes ids binnenkomen, dan krijgen die drie positie
         * 1, 2 en 3 en botsen ze met de andere drie.
         */
        $bestaand = AboutPoint::query()->orderBy('id')->pluck('id')->all();
        $gekregen = array_map(fn (array $regel) => $regel['id'], $rij);
        sort($gekregen);

        if ($gekregen !== $bestaand) {
            Toast::fout(__('De lijst klopt niet meer. Ververs de pagina en probeer het opnieuw.'));

            return back();
        }

        $veranderd = DB::transaction(function () use ($rij): bool {
            $veranderd = false;

            foreach ($rij as $index => $regel) {
                $punt = AboutPoint::query()->findOrFail($regel['id']);

                $punt->position = $index + 1;

                if ($punt->isDirty()) {
                    $veranderd = true;
                    $punt->save();
                }
            }

            return $veranderd;
        });

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De volgorde van je punten is aangepast.'));

        return back();
    }

    /** De kop boven het korte blok op de voorpagina. */
    public function kop(Request $request): RedirectResponse
    {
        if (! $this->verwerkKoptekst($request)) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De kop boven je "Over mij" is aangepast.'));

        return back();
    }

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::OverMij;
    }

    /**
     * Wat er bij een opslag met `photo_path` moet gebeuren.
     *
     * Drie gevallen, en het middelste is het belangrijkste: **er kwam niets
     * mee en er is niets gevraagd, dus we laten het staan.** Dat blijft
     * nodig nu de foto zijn eigen venster heeft: de kiezer stuurt bij een
     * opslag zonder nieuw bestand ook geen bestand mee, en dan hoort de
     * bestaande foto te blijven staan in plaats van te verdwijnen. Zelfde
     * afweging als bij een certificaatlogo.
     *
     * @return array<string, string|null>
     */
    private function fotoVeld(AboutFotoRequest $request, Logo $logo): array
    {
        $bestand = $request->logo();

        if ($bestand !== null) {
            return [
                'photo_path' => $logo->bewaar(
                    $bestand,
                    AboutSetting::SCHIJF,
                    AboutSetting::MAP,
                    $request->uitsnede(),
                    AboutSetting::FOTO_MAAT,
                ),
            ];
        }

        return $request->wilLogoWeg() ? ['photo_path' => null] : [];
    }

    /**
     * Eén punt, zoals het beheerscherm het nodig heeft.
     *
     * @return array<string, mixed>
     */
    private function puntRij(AboutPoint $punt): array
    {
        return [
            'id' => $punt->id,

            // SortableList werkt met tekstsleutels; zie dat component.
            'key' => (string) $punt->id,

            'text_nl' => $punt->text_nl,
            'text_en' => $punt->text_en,
        ];
    }
}
