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
             *
             * Dat het óók in de gedeelde props staat is geen dubbeling om
             * weg te halen: een Inertia-pagina noemt zijn eigen props, en
             * dat leest beter dan een scherm dat stilzwijgend uit de
             * gedeelde verzameling graait. De waarde komt uit dezelfde
             * config, dus ze kunnen niet uiteen lopen.
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
            (string) __('De zin onder je logo is aangepast.'),
            'tagline_translated_at',
            $request->automatischVertaald(),
        );
    }

    /** Telefoon en werkgebied. */
    public function contact(FooterContactRequest $request): RedirectResponse
    {
        return $this->bewaar(
            $request->gegevens(),
            (string) __('Je contactgegevens in de voet zijn aangepast.'),
            'region_translated_at',
            $request->automatischVertaald(),
        );
    }

    /**
     * KvK en btw.
     *
     * Geen merkje: er valt in dit venster niets te vertalen, dus deze
     * opslag zegt niets over het Engels dat elders staat. Daarom geeft hij
     * geen kolomnaam mee en blijven allebei de merkjes staan zoals ze
     * stonden.
     */
    public function bedrijf(FooterBedrijfRequest $request): RedirectResponse
    {
        return $this->bewaar(
            $request->gegevens(),
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
     * **Elk venster raakt alleen zijn eigen merkje aan.** Daar zat de fout:
     * er was één kolom voor twee teksten, dus het contactvenster wiste het
     * merkje van de tagline. Zie de migratie die ze heeft gesplitst.
     *
     * @param  array<string, mixed>  $gegevens
     * @param  string|null  $merkKolom  welk merkje dit venster mag zetten
     * @param  bool|null  $automatischVertaald  `null` laat het merkje staan
     */
    private function bewaar(
        array $gegevens,
        string $melding,
        ?string $merkKolom = null,
        ?bool $automatischVertaald = null,
    ): RedirectResponse {
        $instelling = FooterSetting::query()->firstOrNew();

        $instelling->fill($gegevens);

        if ($merkKolom !== null && $automatischVertaald !== null) {
            /*
             * Het merkje hangt aan de tekst en niet aan deze opslag: het
             * zegt dat het Engels dat er nú staat van de vertaaldienst komt
             * en nog door niemand is nagelezen.
             */
            $instelling->{$merkKolom} = $automatischVertaald
                ? ($instelling->{$merkKolom} ?? Carbon::now())
                : null;
        }

        /*
         * **Een lege eerste opslag is geen wijziging.** `firstOrNew` geeft
         * een model dat nog niet bestaat, en dan is álles "nieuw" -- ook
         * een formulier waarin niets is ingevuld. Zonder deze controle
         * kreeg de eigenaar op een verse installatie een lege rij in de
         * database en de melding dat er iets was aangepast.
         */
        $veranderd = $instelling->exists
            ? $instelling->isDirty()
            : collect($gegevens)->contains(fn (mixed $waarde) => filled($waarde));

        if (! $veranderd && ! $instelling->exists) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        $instelling->save();

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt($melding);

        return back();
    }
}
