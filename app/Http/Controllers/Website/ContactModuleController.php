<?php

namespace App\Http\Controllers\Website;

use App\Enums\ContactVeld;
use App\Enums\ContactVeldStatus;
use App\Enums\ContactWeergave;
use App\Enums\PageSectionKey;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Website\Concerns\BewaartKoptekst;
use App\Http\Requests\Website\ContactInstellingRequest;
use App\Http\Requests\Website\ContactSubjectRequest;
use App\Models\ContactField;
use App\Models\ContactSetting;
use App\Models\ContactSubject;
use App\Support\Toast;
use App\Support\Translation\Vertaler;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De module Contact: de onderwerpen, de velden en de instellingen.
 *
 * **Dit scherm beheert het formulier, niet de aanvragen.** Die staan onder
 * Beheer → Aanvragen, want dat is iets dat je naslaat en niet iets dat je
 * maakt -- dezelfde verdeling als tussen website.php en admin.php.
 *
 * Drie dingen die hier anders liggen dan bij de andere modules:
 *
 * **De velden zijn geen lijst die je aanmaakt.** Er is één rij per
 * `ContactVeld`, neergezet door de seeder. De klant zet ze aan, uit of op
 * verplicht; hij maakt er geen bij. Wat een veld is staat in de enum.
 *
 * **Drie velden zijn vergrendeld.** Naam, e-mailadres en bericht staan
 * altijd aan en altijd op verplicht, want zonder die drie kun je niemand
 * antwoorden. Dat wordt hier serverseitig geweigerd en niet alleen in het
 * scherm uitgezet.
 *
 * **Er zijn twee instellingenvensters.** De weergave (formulier op de
 * pagina of een eigen pagina) en de tekst van de bevestigingsmail. Ze
 * schrijven naar dezelfde rij en sturen elk alleen hun eigen velden mee.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactModuleController extends Controller
{
    use BewaartKoptekst;

    protected function sectie(): PageSectionKey
    {
        return PageSectionKey::Contact;
    }

    public function index(Vertaler $vertaler): Response
    {
        $onderwerpen = ContactSubject::query()->opVolgorde()->get();
        $instellingen = ContactSetting::huidige();

        return Inertia::render('website/Contact', [
            'onderwerpen' => $onderwerpen
                ->map(fn (ContactSubject $onderwerp) => $this->rij($onderwerp))
                ->all(),

            'velden' => $this->veldenVoorHetScherm(),

            /*
             * Of het onderdeel leeg is. Bij de andere modules betekent dat
             * "er staat niets op de site"; hier niet -- het formulier staat
             * er ook zonder onderwerpen. Het zegt dus alleen of er
             * onderwerpen zijn om te kiezen.
             */
            'leeg' => $onderwerpen->isEmpty(),

            'kop' => $this->koptekst()->voorHetScherm(),

            'instellingen' => [
                'weergave' => $instellingen->display->value,
                'bevestigingOnderwerpNl' => $instellingen->confirmation_subject_nl,
                'bevestigingOnderwerpEn' => $instellingen->confirmation_subject_en,
                'bevestigingTekstNl' => $instellingen->confirmation_body_nl,
                'bevestigingTekstEn' => $instellingen->confirmation_body_en,
                'automatischVertaald' => $instellingen->machine_translated_at !== null,
            ],

            'opties' => [
                'weergave' => ContactWeergave::opties(),
                'standen' => ContactVeldStatus::opties(),
            ],

            // Zonder werkende vertaaldienst verdwijnt de knop uit het
            // scherm in plaats van een fout te geven als je erop drukt.
            'kanVertalen' => $vertaler->beschikbaar(),
        ]);
    }

    /* --- De onderwerpen --------------------------------------------------- */

    public function store(ContactSubjectRequest $request): RedirectResponse
    {
        $onderwerp = ContactSubject::query()->create([
            ...$request->gegevens(),
            'published' => $request->boolean('published'),

            // Achteraan in de rij. Waar hij komt te staan bepaalt de klant
            // daarna met slepen.
            'position' => (int) ContactSubject::query()->max('position') + 1,

            'machine_translated_at' => $request->automatischVertaald()
                ? Carbon::now()
                : null,
        ]);

        Toast::aangemaakt(
            __('Het onderwerp is toegevoegd.'),
            $onderwerp->published
                ? __('Bezoekers kunnen het nu kiezen.')
                : __('Het staat nog niet op je website; zet het online wanneer je wilt.'),
        );

        return back();
    }

    public function update(ContactSubjectRequest $request, ContactSubject $subject): RedirectResponse
    {
        $subject->update([
            ...$request->gegevens(),

            /*
             * Het merkje "automatisch vertaald" hangt aan de tekst en niet
             * aan deze opslag: het zegt dat het Engels dat er nú staat van
             * de dienst komt en nog door niemand is nagelezen.
             */
            'machine_translated_at' => $request->automatischVertaald()
                ? ($subject->machine_translated_at ?? Carbon::now())
                : null,
        ]);

        if (! $subject->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('Het onderwerp is aangepast.'));

        return back();
    }

    public function destroy(ContactSubject $subject): RedirectResponse
    {
        // De naam vóór het verwijderen ophalen; daarna is hij weg.
        $naam = $subject->label_nl;

        /*
         * Aanvragen die hieraan hingen blijven staan: de verwijzing wordt
         * leeg en de onderwerptekst die zij zelf bewaren blijft. Zie de
         * migratie en ContactSubmission::onderwerpVerdwenen().
         */
        $subject->delete();

        Toast::verwijderd(__('":naam" is verwijderd.', ['naam' => $naam]));

        return back();
    }

    /**
     * Het schuifje online/offline van een onderwerp.
     *
     * Een eigen route, omdat één waarde omzetten niet het hele formulier
     * langs de validatie hoort te sturen.
     */
    public function online(Request $request, ContactSubject $subject): RedirectResponse
    {
        $aan = $request->boolean('published');

        $subject->update(['published' => $aan]);

        Toast::bijgewerkt($aan
            ? __('":naam" staat nu op je website.', ['naam' => $subject->label_nl])
            : __('":naam" staat niet meer op je website.', ['naam' => $subject->label_nl]));

        return back();
    }

    /**
     * De volgorde van de onderwerpen.
     *
     * Opnieuw nummeren vanaf 1 en niet verschuiven, hetzelfde patroon als
     * bij de andere modules: dan kan er geen gat of dubbele positie
     * ontstaan, hoe vaak je ook sleept.
     */
    public function volgorde(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'onderwerpen' => ['required', 'array'],
            'onderwerpen.*.id' => ['required', 'integer', 'exists:contact_subjects,id'],
        ]);

        /** @var array<int, array{id: int}> $rij */
        $rij = $gegevens['onderwerpen'];

        /*
         * De hele lijst moet meekomen, niet een deel ervan. Zou een
         * verzoek met drie van de zes ids binnenkomen, dan krijgen die
         * drie plek 1, 2 en 3 en belandt de rest erachter in een volgorde
         * die niemand heeft gekozen.
         */
        $ids = array_column($rij, 'id');

        if (count($ids) !== ContactSubject::query()->count()) {
            Toast::fout(__('De lijst klopt niet meer. Verfris de pagina en probeer het opnieuw.'));

            return back();
        }

        DB::transaction(function () use ($rij) {
            foreach (array_values($rij) as $plek => $regel) {
                ContactSubject::query()
                    ->whereKey($regel['id'])
                    ->update(['position' => $plek + 1]);
            }
        });

        Toast::bijgewerkt(__('De volgorde is aangepast.'));

        return back();
    }

    /* --- De velden ------------------------------------------------------- */

    /**
     * De stand van de velden opslaan.
     *
     * **Een vast veld wordt hier geweigerd en niet alleen in het scherm
     * uitgezet.** Het schuifje staat daar vergrendeld, maar een verzoek dat
     * rechtstreeks binnenkomt moet dezelfde grens tegenkomen -- anders is
     * de vergrendeling versiering.
     */
    public function velden(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'velden' => ['required', 'array'],
            'velden.*.key' => ['required', 'string', 'exists:contact_fields,key'],
            'velden.*.status' => ['required', 'string', 'in:uit,optioneel,verplicht'],
            'velden.*.allow_custom' => ['sometimes', 'boolean'],
        ]);

        /** @var array<int, array{key: string, status: string, allow_custom?: bool}> $rij */
        $rij = $gegevens['velden'];

        DB::transaction(function () use ($rij) {
            foreach ($rij as $regel) {
                $veld = ContactVeld::from($regel['key']);

                $nieuw = [
                    'status' => $veld->vast()
                        ? ContactVeldStatus::Verplicht->value
                        : $regel['status'],
                ];

                if ($veld === ContactVeld::Onderwerp && array_key_exists('allow_custom', $regel)) {
                    $nieuw['allow_custom'] = $regel['allow_custom'];
                }

                ContactField::query()->where('key', $veld)->update($nieuw);
            }
        });

        Toast::bijgewerkt(__('De velden van je formulier zijn aangepast.'));

        return back();
    }

    /* --- De instellingen ------------------------------------------------- */

    public function instellingen(ContactInstellingRequest $request): RedirectResponse
    {
        $instellingen = ContactSetting::query()->first()
            ?? ContactSetting::query()->create(ContactSetting::standaard());

        $velden = $request->gegevens();

        if ($velden === []) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        // Het merkje alleen aanraken als er tekst is meegestuurd; het
        // weergavevenster gaat niet over vertalingen.
        if (array_key_exists('confirmation_subject_nl', $velden)) {
            $velden['machine_translated_at'] = $request->automatischVertaald()
                ? ($instellingen->machine_translated_at ?? Carbon::now())
                : null;
        }

        $instellingen->update($velden);

        if (! $instellingen->wasChanged()) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De instellingen zijn aangepast.'));

        return back();
    }

    public function kop(Request $request): RedirectResponse
    {
        if (! $this->verwerkKoptekst($request)) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        Toast::bijgewerkt(__('De kop boven je contactformulier is aangepast.'));

        return back();
    }

    /* --- Hulp ------------------------------------------------------------ */

    /**
     * De velden zoals het beheerscherm ze toont.
     *
     * @return array<int, array<string, mixed>>
     */
    private function veldenVoorHetScherm(): array
    {
        return ContactField::query()
            ->opVolgorde()
            ->get()
            ->map(fn (ContactField $veld) => [
                'key' => $veld->key->value,
                'label' => $veld->key->label(),
                'status' => $veld->stand()->value,
                'vast' => $veld->key->vast(),
                'eigenToegestaan' => $veld->allow_custom,
                'heeftEigenKeuze' => $veld->key === ContactVeld::Onderwerp,
            ])
            ->all();
    }

    /**
     * Eén onderwerp, met beide talen los voor het beheerscherm.
     *
     * @return array<string, mixed>
     */
    private function rij(ContactSubject $onderwerp): array
    {
        return [
            'id' => $onderwerp->id,

            // SortableList eist een string als sleutel.
            'key' => (string) $onderwerp->id,

            'label_nl' => $onderwerp->label_nl,
            'label_en' => $onderwerp->label_en,
            'published' => $onderwerp->published,
            'featured' => $onderwerp->featured,
            'automatischVertaald' => $onderwerp->machine_translated_at !== null,
            'aanvragen' => $onderwerp->aanvragen(),
        ];
    }
}
