<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Http\Requests\Website\FooterBedrijfRequest;
use App\Http\Requests\Website\FooterContactRequest;
use App\Http\Requests\Website\FooterTekstRequest;
use App\Models\FooterSetting;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De voet van de publieke site.
 *
 * **Niet alles in een voet hoort beheerbaar te zijn**, en dat is hier geen
 * bezuiniging maar het ontwerp. De indeling, de kleuren, het jaartal, de
 * privacylink, de sitemap en de credit liggen vast. Wat de eigenaar invult
 * zijn de paar dingen die over zíjn bedrijf gaan en die niemand anders kan
 * weten.
 *
 * | Handeling    | Welk venster                     |
 * | ------------ | -------------------------------- |
 * | `tekst()`    | De zin onder zijn merk           |
 * | `contact()`  | Telefoon en werkgebied           |
 * | `bedrijf()`  | KvK en btw                       |
 *
 * **Drie vensters, drie eindpunten, en geen gedeelde `update()`.** Dat is de
 * les die bij "Over mij" is opgeschreven: daar zat eerst één `update()` voor
 * twee vensters, en bewaarde je het ene dan gingen de velden van het andere
 * als leeg mee en werden ze gewist. Zie AboutController.
 *
 * **En geen `BewaartKoptekst`.** Elke andere module heeft een "Kop erboven",
 * want elke andere module ís een sectie op de pagina met een titel erboven.
 * Een voet heeft geen kop; hij staat onder de streep. `SectionHeading` krijgt
 * dus ook geen arm voor dit onderdeel.
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
class FooterController extends Controller
{
    public function index(Vertaler $vertaler): Response
    {
        $instelling = FooterSetting::huidige();

        return Inertia::render('website/Voettekst', [
            'instelling' => $instelling->voorHetScherm(),

            /*
             * Het adres dat de bezoeker in de voet ziet staan. Het gaat mee
             * als gegeven en niet als veld: het scherm toont het zodat de
             * eigenaar weet wat er staat, maar hij kan het hier niet
             * wijzigen. Zie FooterContactRequest voor waarom niet.
             */
            'email' => (string) config('site.email'),

            'opties' => [
                // Dezelfde grenzen als de validatie, zodat het scherm geen
                // teller kan tonen die de server niet kent.
                'taglineMax' => FooterSetting::TAGLINE_MAX,
                'plaatsMax' => FooterSetting::PLAATS_MAX,
                'telefoonMax' => FooterSetting::TELEFOON_MAX,
                'nummerMax' => FooterSetting::NUMMER_MAX,
                'kvkCijfers' => FooterSetting::KVK_CIJFERS,
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    /** De zin onder zijn merk, in beide talen. */
    public function tekst(FooterTekstRequest $request): RedirectResponse
    {
        return $this->bewaar(
            $request->gegevens(),
            $request->automatischVertaald(),
            (string) __('De zin onder je logo is aangepast.'),
        );
    }

    /** Telefoon en werkgebied. */
    public function contact(FooterContactRequest $request): RedirectResponse
    {
        return $this->bewaar(
            $request->gegevens(),
            $request->automatischVertaald(),
            (string) __('Je contactgegevens in de voet zijn aangepast.'),
        );
    }

    /**
     * KvK en btw.
     *
     * Het merkje "automatisch vertaald" blijft hier staan zoals het stond:
     * er zit in dit venster niets te vertalen, dus deze opslag zegt niets
     * over het Engels dat elders is ingevuld.
     */
    public function bedrijf(FooterBedrijfRequest $request): RedirectResponse
    {
        return $this->bewaar(
            $request->gegevens(),
            null,
            (string) __('Je bedrijfsgegevens in de voet zijn aangepast.'),
        );
    }

    /**
     * De opslag die de drie vensters delen.
     *
     * Wat ze delen is het mechaniek -- één rij, `firstOrNew`, kijken of er
     * iets veranderde -- en niet de velden. Elk venster geeft alleen zijn
     * eigen velden mee, en daar zit het hele verschil met de fout die bij
     * "Over mij" is rechtgezet.
     *
     * @param  array<string, mixed>  $gegevens
     * @param  bool|null  $automatischVertaald  `null` laat het merkje staan
     */
    private function bewaar(
        array $gegevens,
        ?bool $automatischVertaald,
        string $melding,
    ): RedirectResponse {
        $instelling = FooterSetting::query()->firstOrNew();

        $instelling->fill($gegevens);

        if ($automatischVertaald !== null) {
            /*
             * Het merkje hangt aan de tekst en niet aan deze opslag: het
             * zegt dat het Engels dat er nú staat van de vertaaldienst komt
             * en nog door niemand is nagelezen.
             */
            $instelling->machine_translated_at = $automatischVertaald
                ? ($instelling->machine_translated_at ?? Carbon::now())
                : null;
        }

        $veranderd = ! $instelling->exists || $instelling->isDirty();

        $instelling->save();

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt($melding);

        return back();
    }
}
