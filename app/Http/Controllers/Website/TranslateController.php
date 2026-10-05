<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\Service;
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

            // De koppen boven een blok hebben een opschrift; de kop van
            // de landingspagina en die boven de diensten allebei.
            'eyebrow_nl' => ['nullable', 'string', 'max:60'],

            // De korte tekst op een dienstkaart. Apart van `intro_nl`,
            // want die is de zin onder een kop en niet de tekst van een
            // item -- het scherm moet weten waar het antwoord heen moet.
            'summary_nl' => ['nullable', 'string', 'max:300'],

            // Eén expertisepunt onder een dienst, voor het knopje naast
            // dat ene veld. Het venster onthoudt zelf welk punt het was.
            'punt_nl' => ['nullable', 'string', 'max:60'],

            /*
             * En dezelfde punten als lijst, voor de grote knop.
             *
             * Die twee bestaan naast elkaar omdat het twee verschillende
             * handelingen zijn: "vertaal dit ene woord opnieuw" en
             * "vertaal alles van deze dienst". Zonder deze lijst zou de
             * grote knop de titel en de teksten wél meenemen en de
             * punten eronder niet, en dat is precies het soort halve
             * uitkomst waar je later achter komt.
             */
            'punten_nl' => ['nullable', 'array', 'max:'.Service::PUNTEN_MAXIMUM],
            'punten_nl.*' => ['nullable', 'string', 'max:60'],

            // Het woord onder één cijfer boven de tijdlijn. Eén veld en
            // geen lijst: de knop staat per cijfer, en het venster
            // onthoudt zelf welk cijfer het vroeg.
            'woord_nl' => ['nullable', 'string', 'max:40'],

            /*
             * Het niveau van een opleiding: "MBO niveau 4". Het enige
             * nieuwe veld dat de certificaten nodig hadden -- de naam
             * van een certificaat gaat door `title_nl` en de
             * toelichting door `body_nl`, allebei velden die er al
             * waren. Zie docs/architecture/automatisch-vertalen.md.
             */
            'niveau_nl' => ['nullable', 'string', 'max:60'],

            // De toelichting bij een certificaat. Apart van
            // `description_nl`, want die hoort bij een ervaring en het
            // scherm moet weten waar het antwoord heen moet.
            'body_nl' => ['nullable', 'string', 'max:2000'],

            /*
             * De drie velden van een statistiek: de naam, het regeltje
             * eronder en de groep. Drie eigen velden en niet `title_nl`
             * hergebruikt, want dit venster stuurt ze alle drie
             * tegelijk -- en dan moet het antwoord ze uit elkaar kunnen
             * houden.
             */
            /*
             * Tachtig en niet zestig tekens: dit veld doet sinds de
             * module Contact ook de onderwerpen van het contactformulier,
             * en die mogen iets langer dan het label van een statistiek.
             */
            'label_nl' => ['nullable', 'string', 'max:80'],
            'notitie_nl' => ['nullable', 'string', 'max:120'],
            'groep_nl' => ['nullable', 'string', 'max:60'],
        ]);

        /** @var array<int, string|null> $punten */
        $punten = $bron['punten_nl'] ?? [];
        unset($bron['punten_nl']);

        try {
            $vertaald = $vertaler->naarEngels([
                ...$bron,
                ...$this->alsVelden($punten),
            ]);
        } catch (VertaalFout $fout) {
            Toast::fout($fout->melding());

            return back();
        }

        Inertia::flash('vertaling', $this->alsAntwoord($vertaald));

        return back();
    }

    /**
     * De lijst met punten plat maken tot losse velden.
     *
     * De vertaler werkt met een platte lijst van veldnaam naar tekst, en
     * dat is met opzet: hij weet niets van onze schermen. Het nummer gaat
     * in de veldnaam mee, zodat het antwoord straks weer bij het goede
     * punt terechtkomt.
     *
     * @param  array<int, string|null>  $punten
     * @return array<string, string|null>
     */
    private function alsVelden(array $punten): array
    {
        $velden = [];

        foreach ($punten as $index => $tekst) {
            $velden['punt'.$index.'_nl'] = $tekst;
        }

        return $velden;
    }

    /**
     * Het antwoord zoals het formulier het nodig heeft.
     *
     * De sleutels gaan van `_nl` naar `_en`, want dat zijn de velden die
     * het formulier moet invullen. De vertaler weet niets van onze
     * kolomnamen; die vertaling hoort hier.
     *
     * De genummerde punten komen apart terug, onder `punten_en`, **met
     * hun oorspronkelijke nummer als sleutel**. Lege punten gaan niet
     * naar de vertaaldienst en komen er dus ook niet uit: zou dit
     * opnieuw doornummeren, dan schuift de vertaling van punt vier naar
     * punt drie zodra punt twee leeg was.
     *
     * @param  array<string, string>  $vertaald
     * @return array<string, mixed>
     */
    private function alsAntwoord(array $vertaald): array
    {
        $velden = [];
        $punten = [];

        foreach ($vertaald as $sleutel => $tekst) {
            if (preg_match('/^punt(\d+)_nl$/', $sleutel, $treffer) === 1) {
                $punten[(int) $treffer[1]] = $tekst;

                continue;
            }

            $velden[str_replace('_nl', '_en', $sleutel)] = $tekst;
        }

        if ($punten !== []) {
            /*
             * Als object en niet als lijst, want de nummers kunnen gaten
             * hebben. In JSON wordt een array met gaten vanzelf een
             * object, en dat is precies wat het formulier verwacht.
             */
            $velden['punten_en'] = $punten;
        }

        return $velden;
    }
}
