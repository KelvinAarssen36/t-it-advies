<?php

namespace App\Http\Controllers\Website;

use App\Enums\PageSectionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Http\Requests\Website\LinkedinKnopRequest;
use App\Models\LinkedinSetting;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het LinkedIn-blok: de teksten, niet de link.
 *
 * | Handeling | Wat                                        | Waar het staat     |
 * | --------- | ------------------------------------------ | ------------------ |
 * | `kop()`   | Opschrift, titel en de zin eronder         | `section_headings` |
 * | `knop()`  | De tekst op de knop                        | `linkedin_settings`|
 *
 * **De link ligt vast**, in config/site.php. Hij gaat mee naar het scherm
 * als gegeven, zodat de eigenaar ziet waar de knop heen wijst, maar er is
 * geen veld voor: een tikfout in een link naar buiten is een dode knop op
 * de voorpagina, en een tweede plek om hem in te vullen geeft twee
 * waarheden. Zie docs/architecture/modules/linkedin.md.
 *
 * Twee vensters, twee eindpunten, net als bij de voettekst: zo kan het
 * ene venster nooit de velden van het andere wissen.
 */
class LinkedinController extends Controller
{
    use BewaartKoptekst;

    public function index(Vertaler $vertaler): Response
    {
        return Inertia::render('website/Linkedin', [
            'kop' => $this->koptekst()->voorHetScherm(),
            'instelling' => LinkedinSetting::huidige()->voorHetScherm(),

            // Ter informatie: waar de knop heen gaat, en wat hij zegt als
            // de eigenaar niets invult. Allebei geen veld.
            'adres' => (string) config('site.linkedin'),
            'standaardKnop' => [
                'nl' => $this->standaardKnop('nl'),
                'en' => $this->standaardKnop('en'),
            ],

            'opties' => [
                'knopMax' => LinkedinSetting::KNOP_MAX,
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    /** Het opschrift, de titel en de zin erboven. */
    public function kop(Request $request): RedirectResponse
    {
        if (! $this->verwerkKoptekst($request)) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De tekst van je LinkedIn-blok is aangepast.'));

        return back();
    }

    /** De tekst op de knop, in beide talen. */
    public function knop(LinkedinKnopRequest $request): RedirectResponse
    {
        $instelling = LinkedinSetting::query()->firstOrNew();

        $instelling->fill($request->gegevens());

        /*
         * Het merkje hangt aan de tekst en niet aan deze opslag; zie
         * docs/architecture/automatisch-vertalen.md. Zonder Engels valt er
         * niets na te lezen, dus dan is er ook geen merkje.
         */
        $instelling->button_translated_at = $request->automatischVertaald() && filled($instelling->button_en)
            ? ($instelling->button_translated_at ?? Carbon::now())
            : null;

        /*
         * Een lege eerste opslag is geen wijziging: dan blijft de
         * standaardtekst staan en komt er geen lege rij bij.
         */
        $veranderd = $instelling->exists
            ? $instelling->isDirty()
            : collect($request->gegevens())->contains(fn (?string $waarde) => filled($waarde));

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        $instelling->save();

        Toast::bijgewerkt(__('De tekst op je LinkedIn-knop is aangepast.'));

        return back();
    }

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::Linkedin;
    }

    /** Wat de knop zegt zolang er niets is ingevuld, in een bepaalde taal. */
    private function standaardKnop(string $taal): string
    {
        $vorige = app()->getLocale();
        app()->setLocale($taal);

        try {
            return (new LinkedinSetting)->knop((string) config('security.portal_account.name'));
        } finally {
            app()->setLocale($vorige);
        }
    }
}
