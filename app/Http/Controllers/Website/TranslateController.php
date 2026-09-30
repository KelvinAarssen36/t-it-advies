<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Support\Toast;
use App\Support\Translation\VertaalFout;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * De knop "Vertaal automatisch", voor elk beheerscherm van de website.
 *
 * **Eén route voor alle modules, en dat is een afspraak en geen gemak.**
 * Deze knop stond eerst op `ExperienceController`, onder de routes van de
 * tijdlijn. Dat werkte prima tot de kop van de landingspagina hem ook
 * nodig had: die zou dan naar `website/ervaring/vertalen` moeten posten,
 * en dat is een adres dat liegt over waar je bent.
 *
 * Een tweede route ernaast is het alternatief, en dat is erger. Dan staan
 * dezelfde begrenzing, dezelfde foutafhandeling en dezelfde vertaling van
 * sleutelnamen op twee plekken, en dat is precies waar twee dingen uiteen
 * gaan lopen zodra iemand er één aanpast.
 *
 * Vandaar: één adres, en een veld per soort tekst dat een module kan
 * sturen. Ze zijn allemaal `nullable`, dus elk scherm stuurt alleen wat
 * het heeft.
 *
 * **Er wordt niets opgeslagen.** De klant krijgt een voorstel in zijn
 * velden, leest het na, past aan wat hij wil, en drukt daarna pas op
 * Opslaan. Zou de knop meteen wegschrijven, dan staat er automatisch
 * vertaald Engels live voordat iemand het heeft gezien.
 *
 * Zie docs/architecture/automatisch-vertalen.md.
 */
class TranslateController extends Controller
{
    public function __invoke(Request $request, Vertaler $vertaler): RedirectResponse
    {
        /*
         * Een 404 en geen foutmelding. Staat het vertalen uit, dan is er
         * geen dienst om aan te roepen -- en dan hoort het adres er niet
         * te zijn in plaats van te bestaan en altijd te falen.
         */
        if (! $vertaler->beschikbaar()) {
            abort(404);
        }

        $bron = $request->validate([
            // De tijdlijn: één ervaring.
            'role_nl' => ['nullable', 'string', 'max:120'],
            'location_nl' => ['nullable', 'string', 'max:120'],
            'description_nl' => ['nullable', 'string', 'max:5000'],

            // De kop boven de tijdlijn én de kop van de landingspagina.
            // Allebei een titel met een zin eronder, dus allebei dezelfde
            // twee velden.
            'title_nl' => ['nullable', 'string', 'max:120'],
            'intro_nl' => ['nullable', 'string', 'max:300'],

            // Alleen de kop van de landingspagina heeft een opschrift.
            'eyebrow_nl' => ['nullable', 'string', 'max:60'],

            // Het woord onder één cijfer boven de tijdlijn. Eén veld en
            // geen lijst: de knop staat per cijfer, en het venster
            // onthoudt zelf welk cijfer het vroeg.
            'woord_nl' => ['nullable', 'string', 'max:40'],
        ]);

        try {
            $vertaald = $vertaler->naarEngels($bron);
        } catch (VertaalFout $fout) {
            Toast::fout($fout->melding());

            return back();
        }

        /*
         * De sleutels gaan van `_nl` naar `_en`, want dat zijn de velden
         * die het formulier moet invullen. De vertaler weet niets van onze
         * kolomnamen; die vertaling hoort hier.
         */
        $velden = [];

        foreach ($vertaald as $sleutel => $tekst) {
            $velden[str_replace('_nl', '_en', $sleutel)] = $tekst;
        }

        Inertia::flash('vertaling', $velden);

        return back();
    }
}
