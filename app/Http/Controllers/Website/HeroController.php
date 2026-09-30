<?php

namespace App\Http\Controllers\Website;

use App\Http\Controllers\Controller;
use App\Models\HeroHeading;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De kop van de landingspagina: drie teksten, en meer niet.
 *
 * **Het kleinste beheerscherm van de website, en dat is de bedoeling.**
 * Er is één kop, dus er valt niets aan te maken en niets te verwijderen.
 * Geen lijst, geen detailpagina, geen zoekveld -- één overzicht met wat
 * er nu staat, en één knop die het bewerkvenster opent.
 *
 * Toch gelden dezelfde afspraken als bij de tijdlijn, en met opzet:
 *
 * - **Bewerken gebeurt in een venster**, niet op de pagina zelf. Alles
 *   wat de klant hier aanpast staat direct live, dus het verschil tussen
 *   "ik kijk" en "ik wijzig" hoort zichtbaar te zijn.
 * - **Twee bevestigingen**, want dit is een bestaand item wijzigen.
 * - **Allebei de talen los**, met de vertaalknop ernaast. Zie
 *   TranslateController.
 * - **Het activiteitenlogboek** loopt mee via HeroHeading; dit is de
 *   eerste tekst die een bezoeker leest.
 *
 * Zie docs/architecture/modules/kop.md.
 */
class HeroController extends Controller
{
    public function index(Vertaler $vertaler): Response
    {
        $kop = HeroHeading::huidige();

        return Inertia::render('website/Kop', [
            /*
             * Allebei de talen los, want dit scherm bewerkt ze allebei.
             * De publieke site krijgt een andere vorm: daar is de keuze
             * tussen Nederlands en Engels al gemaakt. Zie HomeController.
             */
            'kop' => [
                'eyebrow_nl' => $kop->eyebrow_nl,
                'eyebrow_en' => $kop->eyebrow_en,
                'title_nl' => $kop->title_nl,
                'title_en' => $kop->title_en,
                'intro_nl' => $kop->intro_nl,
                'intro_en' => $kop->intro_en,
                'automatisch_vertaald' => $kop->machine_translated_at !== null,
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    /**
     * De drie teksten opslaan.
     *
     * Eén opslag voor alle drie, want op de website is het één blok. Ze
     * los kunnen bewerken zou betekenen dat de eigenaar drie keer
     * bevestigt voor één zichtbare verandering.
     */
    public function update(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            /*
             * Het opschrift en de titel zijn verplicht, de zin eronder
             * niet. Zonder titel staat er een pagina zonder kop; zonder
             * die laatste zin staat er gewoon niets.
             *
             * De lengtes zijn die van de kolommen, en ze zijn krap met
             * reden: dit is een kop en geen alinea. Zie de migratie.
             */
            'eyebrow_nl' => ['required', 'string', 'max:60'],
            'eyebrow_en' => ['nullable', 'string', 'max:60'],
            'title_nl' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],
            'intro_nl' => ['nullable', 'string', 'max:300'],
            'intro_en' => ['nullable', 'string', 'max:300'],

            /*
             * Of het Engels van de vertaaldienst kwam. De frontend zet
             * dit; hij weet als enige of de klant de Engelse tekst daarna
             * nog met de hand heeft aangeraakt.
             */
            'automatisch_vertaald' => ['boolean'],
        ]);

        $kop = HeroHeading::huidige();

        /*
         * Een leeg optioneel veld wordt `null` en geen lege string. Dat
         * verschil bepaalt of de website er een zin onder zet, en het is
         * dezelfde regel als overal; zie docs/development/testen.md.
         */
        $kop->fill([
            'eyebrow_nl' => $gegevens['eyebrow_nl'],
            'eyebrow_en' => blank($gegevens['eyebrow_en'] ?? null) ? null : $gegevens['eyebrow_en'],
            'title_nl' => $gegevens['title_nl'],
            'title_en' => blank($gegevens['title_en'] ?? null) ? null : $gegevens['title_en'],
            'intro_nl' => blank($gegevens['intro_nl'] ?? null) ? null : $gegevens['intro_nl'],
            'intro_en' => blank($gegevens['intro_en'] ?? null) ? null : $gegevens['intro_en'],
        ]);

        /*
         * Het merkje "automatisch vertaald" hangt aan de tekst en niet aan
         * deze opslag: het zegt dat het Engels dat er nú staat van de
         * dienst komt en nog door niemand is nagelezen. Het scherm bepaalt
         * of dat nog waar is -- het weet als enige of de klant daarna nog
         * in de Engelse velden heeft getypt.
         */
        $kop->machine_translated_at = ($gegevens['automatisch_vertaald'] ?? false)
            ? ($kop->machine_translated_at ?? Carbon::now())
            : null;

        /*
         * Niets veranderd is geen opslag waard. Zonder deze controle komt
         * er een regel in het activiteitenlogboek en een melding "het is
         * aangepast" terwijl er niets anders is dan daarvoor.
         *
         * `isDirty()` op een rij die nog niet bestaat is altijd waar, en
         * dat klopt ook: hem voor het eerst wegschrijven ís een wijziging.
         */
        if ($kop->exists && ! $kop->isDirty()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        $kop->save();

        Toast::bijgewerkt(__('De kop van je landingspagina is aangepast.'));

        return back();
    }
}
