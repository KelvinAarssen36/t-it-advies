<?php

namespace App\Http\Controllers\Website;

use App\Enums\PageSectionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Http\Requests\Website\CertificateRequest;
use App\Http\Requests\Website\EducationRequest;
use App\Models\Certificate;
use App\Models\Education;
use App\Support\Datum;
use App\Support\Media\Logo;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De certificaten, en de opleidingen eronder.
 *
 * De vierde module. Van [Diensten](ServiceController.php) komt de lijst
 * met een eigen volgorde en een venster per item; van
 * [Ervaring](ExperienceController.php) het logo met zijn uitsnijvenster
 * en de maandkiezer.
 *
 * **Twee lijsten op één scherm, en dat is met opzet.** Op de website
 * zijn het samen één blok -- het raster met badges, en eronder een
 * regeltje of drie met opleidingen. Ze in twee schermen uit elkaar
 * trekken zou betekenen dat de eigenaar twee plekken moet onthouden voor
 * iets dat hij als één ding ziet.
 *
 * **De opleidingen zijn bewust karig bediend.** Geen logo, geen
 * detailvenster, geen sleepvolgorde. Bij twee of drie opleidingen is
 * "laatst afgeronde bovenaan" altijd goed, en alles daarboven is
 * gereedschap voor een probleem dat niet bestaat.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
class CertificateController extends Controller
{
    use BewaartKoptekst;

    public function index(Vertaler $vertaler): Response
    {
        $certificaten = Certificate::query()->opVolgorde()->get();
        $opleidingen = Education::query()->opPeriode()->get();

        return Inertia::render('website/Certificaten', [
            'items' => $certificaten
                ->map(fn (Certificate $certificaat) => $this->rij($certificaat))
                ->all(),

            'opleidingen' => $opleidingen
                ->map(fn (Education $opleiding) => $this->opleidingRij($opleiding))
                ->all(),

            /*
             * Of het onderdeel leeg is. Allebei de lijsten tellen mee:
             * een blok met alleen een opleiding erin is niet leeg.
             */
            'leeg' => $certificaten->isEmpty() && $opleidingen->isEmpty(),

            'kop' => $this->koptekst()->voorHetScherm(),

            'opties' => [
                'maanden' => Datum::maanden(),
                'jaren' => Datum::jaren(),

                /*
                 * Een aparte lijst voor "geldig tot", want die ligt per
                 * definitie in de toekomst. Vijftien jaar vooruit is
                 * ruim: zo lang gaat geen enkel IT-certificaat mee.
                 */
                'jarenVooruit' => Datum::jaren(15),
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    public function store(CertificateRequest $request, Logo $logo): RedirectResponse
    {
        $certificaat = Certificate::query()->create([
            ...$request->gegevens(),
            'logo_path' => $this->bewaarLogo($request, $logo),

            // Achteraan in de rij. Waar hij komt te staan bepaalt de
            // klant daarna met slepen; hem ergens tussen zetten zou een
            // keuze zijn die wij voor hem maken.
            'position' => (int) Certificate::query()->max('position') + 1,

            'machine_translated_at' => $request->automatischVertaald()
                ? Carbon::now()
                : null,
        ]);

        Toast::aangemaakt(
            __('Het certificaat is toegevoegd.'),
            $certificaat->published
                ? __('Het staat nu op je website.')
                : __('Het staat nog niet op je website; zet het online wanneer je wilt.'),
        );

        return back();
    }

    public function update(
        CertificateRequest $request,
        Certificate $certificate,
        Logo $logo,
    ): RedirectResponse {
        $oudLogo = $certificate->logo_path;

        $certificate->update([
            ...$request->gegevens(),
            ...$this->logoVeld($request, $logo),

            /*
             * Het merkje "automatisch vertaald" hangt aan de tekst en
             * niet aan deze opslag: het zegt dat het Engels dat er nú
             * staat van de dienst komt en nog door niemand is nagelezen.
             * Het scherm bepaalt of dat nog waar is.
             */
            'machine_translated_at' => $request->automatischVertaald()
                ? ($certificate->machine_translated_at ?? Carbon::now())
                : null,
        ]);

        /*
         * Het oude bestand pas weggooien als het opslaan gelukt is, en
         * alleen als het écht is vervangen. Andersom -- eerst weg, dan
         * opslaan -- houd je bij een mislukte opslag een certificaat over
         * dat naar een bestand wijst dat er niet meer is.
         */
        if ($oudLogo !== null && $certificate->logo_path !== $oudLogo) {
            Storage::disk(Certificate::SCHIJF)->delete($oudLogo);
        }

        if (! $certificate->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('Het certificaat is aangepast.'));

        return back();
    }

    public function destroy(Certificate $certificate): RedirectResponse
    {
        // De naam vóór het verwijderen ophalen; daarna is hij weg. Het
        // logo gaat mee via de haak op het model.
        $naam = $certificate->title_nl;

        $certificate->delete();

        Toast::verwijderd(__('":naam" is verwijderd.', ['naam' => $naam]));

        return back();
    }

    /**
     * Het schuifje online/offline.
     *
     * Een eigen route, omdat één waarde omzetten niet het hele formulier
     * langs de validatie hoort te sturen.
     */
    public function online(Request $request, Certificate $certificate): RedirectResponse
    {
        $aan = $request->boolean('published');

        $certificate->update(['published' => $aan]);

        Toast::bijgewerkt($aan
            ? __('":naam" staat nu op je website.', ['naam' => $certificate->title_nl])
            : __('":naam" staat niet meer op je website.', ['naam' => $certificate->title_nl]));

        return back();
    }

    /**
     * De volgorde van de certificaten.
     *
     * Opnieuw nummeren vanaf 1 en niet verschuiven, hetzelfde patroon als
     * bij de diensten en bij de indeling van de pagina: dan kan er geen
     * gat of dubbele positie ontstaan, hoe vaak je ook sleept.
     */
    public function volgorde(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'certificaten' => ['required', 'array'],
            'certificaten.*' => ['required', 'integer', 'exists:certificates,id'],
        ]);

        /** @var array<int, int> $rij */
        $rij = $gegevens['certificaten'];

        /*
         * De hele lijst moet meekomen, niet een deel ervan. Zou een
         * verzoek met drie van de zes ids binnenkomen, dan krijgen die
         * drie positie 1, 2 en 3 en botsen ze met de andere drie.
         */
        $bestaand = Certificate::query()->orderBy('id')->pluck('id')->all();
        $gekregen = $rij;
        sort($gekregen);

        if ($gekregen !== $bestaand) {
            Toast::fout(__('De lijst klopt niet meer. Ververs de pagina en probeer het opnieuw.'));

            return back();
        }

        $veranderd = DB::transaction(function () use ($rij) {
            $veranderd = false;

            foreach ($rij as $index => $id) {
                $certificaat = Certificate::query()->findOrFail($id);
                $certificaat->position = $index + 1;

                if ($certificaat->isDirty()) {
                    $veranderd = true;
                }

                $certificaat->save();
            }

            return $veranderd;
        });

        if (! $veranderd) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De volgorde van je certificaten is aangepast.'));

        return back();
    }

    /** De kop boven het hele blok. */
    public function kop(Request $request): RedirectResponse
    {
        if (! $this->verwerkKoptekst($request)) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De kop boven je certificaten is aangepast.'));

        return back();
    }

    public function opleidingStore(EducationRequest $request): RedirectResponse
    {
        $opleiding = Education::query()->create([
            ...$request->gegevens(),
            'machine_translated_at' => $request->automatischVertaald()
                ? Carbon::now()
                : null,
        ]);

        Toast::aangemaakt(
            __('De opleiding is toegevoegd.'),
            $opleiding->published
                ? __('Hij staat nu op je website.')
                : __('Hij staat nog niet op je website; zet hem online wanneer je wilt.'),
        );

        return back();
    }

    public function opleidingUpdate(EducationRequest $request, Education $education): RedirectResponse
    {
        $education->update([
            ...$request->gegevens(),
            'machine_translated_at' => $request->automatischVertaald()
                ? ($education->machine_translated_at ?? Carbon::now())
                : null,
        ]);

        if (! $education->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De opleiding is aangepast.'));

        return back();
    }

    public function opleidingDestroy(Education $education): RedirectResponse
    {
        $naam = $education->title_nl;

        $education->delete();

        Toast::verwijderd(__('":naam" is verwijderd.', ['naam' => $naam]));

        return back();
    }

    /** Het schuifje online/offline bij een opleiding. */
    public function opleidingOnline(Request $request, Education $education): RedirectResponse
    {
        $aan = $request->boolean('published');

        $education->update(['published' => $aan]);

        Toast::bijgewerkt($aan
            ? __('":naam" staat nu op je website.', ['naam' => $education->title_nl])
            : __('":naam" staat niet meer op je website.', ['naam' => $education->title_nl]));

        return back();
    }

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::Certificaten;
    }

    /**
     * Eén certificaat, zoals het beheerscherm het nodig heeft.
     *
     * Allebei de talen los, want dit scherm bewerkt ze allebei. De
     * publieke site krijgt een andere vorm: daar is de keuze tussen
     * Nederlands en Engels al gemaakt. Zie HomeController.
     *
     * @return array<string, mixed>
     */
    private function rij(Certificate $certificaat): array
    {
        return [
            'id' => $certificaat->id,

            // SortableList werkt met tekstsleutels; zie dat component.
            'key' => (string) $certificaat->id,

            'published' => $certificaat->published,
            'logo' => $certificaat->logo(),
            'title_nl' => $certificaat->title_nl,
            'title_en' => $certificaat->title_en,
            'issuer' => $certificaat->issuer,

            // Het formulier werkt met "2024-03"; de lijst leest
            // "maart 2024". Allebei meesturen scheelt de frontend een
            // datumbewerking die hij in twee talen goed moet doen.
            'issued_on' => $certificaat->issued_on->format('Y-m'),
            'behaald' => Datum::maand($certificaat->issued_on),
            'expires_on' => $certificaat->expires_on?->format('Y-m'),
            'geldig_tot' => Datum::maand($certificaat->expires_on),
            'verlopen' => $certificaat->verlopen(),

            'credential_id' => $certificaat->credential_id,
            'body_nl' => $certificaat->body_nl,
            'body_en' => $certificaat->body_en,
            'automatisch_vertaald' => $certificaat->machine_translated_at !== null,
        ];
    }

    /**
     * Eén opleiding, zoals het beheerscherm hem nodig heeft.
     *
     * @return array<string, mixed>
     */
    private function opleidingRij(Education $opleiding): array
    {
        return [
            'id' => $opleiding->id,
            'published' => $opleiding->published,
            'title_nl' => $opleiding->title_nl,
            'title_en' => $opleiding->title_en,
            'institution' => $opleiding->institution,
            'level_nl' => $opleiding->level_nl,
            'level_en' => $opleiding->level_en,
            'started_on' => $opleiding->started_on->format('Y-m'),
            'ended_on' => $opleiding->ended_on?->format('Y-m'),
            'periode' => $opleiding->periode(),
            'automatisch_vertaald' => $opleiding->machine_translated_at !== null,
        ];
    }

    /**
     * Het nieuwe logo opslaan, als er een meekwam.
     *
     * Het verkleinen en het verzinnen van de bestandsnaam gebeurt in
     * App\Support\Media\Logo; hier staat alleen wanneer dat gebeurt.
     */
    private function bewaarLogo(CertificateRequest $request, Logo $logo): ?string
    {
        $bestand = $request->logo();

        return $bestand === null
            ? null
            : $logo->bewaar(
                $bestand,
                Certificate::SCHIJF,
                Certificate::MAP,
                $request->uitsnede(),
            );
    }

    /**
     * Wat er bij een wijziging met `logo_path` moet gebeuren.
     *
     * Drie gevallen, en het middelste is het belangrijkste: **er kwam
     * niets mee en er is niets gevraagd, dus we laten het staan.** Zou
     * dit veld er altijd in zitten, dan raakt de klant zijn logo kwijt
     * zodra hij een typefout in de naam verbetert.
     *
     * @return array<string, string|null>
     */
    private function logoVeld(CertificateRequest $request, Logo $logo): array
    {
        $nieuw = $this->bewaarLogo($request, $logo);

        if ($nieuw !== null) {
            return ['logo_path' => $nieuw];
        }

        return $request->wilLogoWeg() ? ['logo_path' => null] : [];
    }
}
