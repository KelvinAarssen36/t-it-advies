<?php

namespace App\Http\Requests\Website;

use App\Enums\EmploymentType;
use App\Enums\ExperienceIcon;
use App\Enums\WorkplaceType;
use App\Support\Media\Uitsnede;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/**
 * De invoer van één ervaring, voor zowel aanmaken als wijzigen.
 *
 * **De periode komt binnen als "2021-03" en niet als volledige datum.** Het
 * formulier laat maand en jaar kiezen, want dat is wat er bekend is; de dag
 * zou een precisie suggereren die er niet is. Hier wordt daar de eerste van
 * de maand van gemaakt, zodat de database gewoon een datumkolom kan zijn
 * waarop je kunt sorteren en vergelijken.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal` staat op
 * de hele groep in routes/website.php; zie docs/security/rollen-en-rechten.md.
 */
class ExperienceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'icon' => ['required', Rule::enum(ExperienceIcon::class)],

            /*
             * Of de ervaring op de website staat. Een nieuwe ervaring krijgt
             * de keuze in het bevestigingsvenster mee; bij het wijzigen komt
             * hij gewoon terug zoals hij stond.
             */
            'published' => ['boolean'],

            /*
             * Het logo van de organisatie. Drie grenzen, elk met een eigen
             * reden:
             *
             * - `image` en `mimes` samen: `image` kijkt naar de inhoud van
             *   het bestand, `mimes` naar de extensie. Allebei, want de
             *   eerste houdt een hernoemd script tegen en de tweede houdt
             *   formaten buiten de deur die wel een afbeelding zijn maar
             *   niet in elke browser werken.
             * - `max`: meer heeft een logo nooit nodig, en het scheelt de
             *   klant een upload die op een trage verbinding afbreekt.
             * - `dimensions` met een maximum: GD zet een afbeelding uitgepakt
             *   in het geheugen, vier bytes per beeldpunt. Een plaatje van
             *   20.000 bij 20.000 past binnen twee megabyte op schijf, maar
             *   vraagt ruim een gigabyte werkgeheugen. Zonder deze grens is
             *   dat een manier om de server om te duwen.
             *
             * De getallen staan in config/media.php, want ze moeten ook
             * kloppen met wat de browser vooraf doet en met wat de
             * hostingomgeving toestaat. Eén bron dus, en niet drie.
             */
            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.$this->maxKilobytes(),
                sprintf(
                    'dimensions:min_width=%1$d,min_height=%1$d,max_width=%2$d,max_height=%2$d',
                    $this->minZijde(),
                    $this->maxZijde(),
                ),
            ],

            /** Het bestaande logo weghalen zonder er een nieuw voor terug. */
            'logo_verwijderen' => ['boolean'],

            /*
             * Hoe de klant het beeld in het ronde vakje heeft gezet. De
             * grenzen staan hier én in App\Support\Media\Uitsnede: die
             * laatste is de laatste halte voordat GD ermee gaat rekenen, en
             * een zoom van nul levert daar geen foutmelding op maar een
             * onzinnig plaatje.
             */
            'logo_zoom' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'logo_x' => ['nullable', 'numeric', 'min:-1', 'max:1'],
            'logo_y' => ['nullable', 'numeric', 'min:-1', 'max:1'],
            'logo_plaat' => ['boolean'],

            'role_nl' => ['required', 'string', 'max:120'],
            'role_en' => ['nullable', 'string', 'max:120'],

            'organisation' => ['required', 'string', 'max:120'],
            // `url` en niet alleen `string`: een link die het niet doet is
            // vervelender dan geen link, en dit is de enige plek waar je
            // hem kunt tegenhouden.
            'organisation_url' => ['nullable', 'url:http,https', 'max:255'],

            'employment' => ['nullable', Rule::enum(EmploymentType::class)],
            'workplace' => ['nullable', Rule::enum(WorkplaceType::class)],

            'location_nl' => ['nullable', 'string', 'max:120'],
            'location_en' => ['nullable', 'string', 'max:120'],

            'started_on' => ['required', 'date_format:Y-m'],
            /*
             * Leeg betekent "tot heden", en dat is een geldig antwoord.
             * `after_or_equal` vangt de omgekeerde periode af: zonder die
             * regel kun je een ervaring invoeren die eindigt voordat hij
             * begint, en dan klopt de hele tijdlijn niet meer.
             */
            'ended_on' => ['nullable', 'date_format:Y-m', 'after_or_equal:started_on'],

            'description_nl' => ['nullable', 'string', 'max:5000'],
            'description_en' => ['nullable', 'string', 'max:5000'],

            /*
             * Zet de browser als de klant op "Vertaal automatisch" drukte en
             * daarna niets meer in de Engelse velden wijzigde. Het is dus
             * een mededeling en geen instelling; wat de server ermee doet
             * staat in ExperienceController.
             */
            'machine_translated' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'logo' => __('logo'),
            'role_nl' => __('functie'),
            'role_en' => __('Engelse functie'),
            'organisation' => __('organisatie'),
            'organisation_url' => __('website'),
            'location_nl' => __('plaats'),
            'location_en' => __('Engelse plaats'),
            'started_on' => __('begindatum'),
            'ended_on' => __('einddatum'),
            'description_nl' => __('beschrijving'),
            'description_en' => __('Engelse beschrijving'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ended_on.after_or_equal' => __('De einddatum kan niet vóór de begindatum liggen.'),
            'logo.dimensions' => __(
                'Dit plaatje is te klein of te groot. Gebruik een afbeelding van minstens :min en hoogstens :max pixels.',
                ['min' => $this->minZijde(), 'max' => $this->maxZijde()],
            ),
            'logo.max' => __(
                'Het logo mag hoogstens :aantal MB zijn.',
                ['aantal' => round($this->maxKilobytes() / 1024, 1)],
            ),
        ];
    }

    /** De grootste upload die we accepteren, in kilobytes. */
    private function maxKilobytes(): int
    {
        return (int) config('media.logo.max_kb', 2048);
    }

    /** De kortste zijde die nog bruikbaar is. */
    private function minZijde(): int
    {
        return (int) config('media.logo.min_zijde', 48);
    }

    /** De langste zijde die we aan GD durven te geven. */
    private function maxZijde(): int
    {
        return (int) config('media.logo.max_zijde', 3000);
    }

    /**
     * De gevalideerde invoer als modelvelden.
     *
     * @return array<string, mixed>
     */
    public function gegevens(): array
    {
        return [
            'icon' => $this->string('icon')->toString(),
            /*
             * Standaard aan als het veld er niet bij zit. Dat is dezelfde
             * keuze als de standaardwaarde in de migratie, en hij is
             * bewust die kant op: een ervaring die je invoert wil je op je
             * website hebben. Zou de terugval `false` zijn, dan verdwijnt
             * alles wat langs een andere weg binnenkomt stilletjes van de
             * site.
             */
            'published' => $this->boolean('published', true),
            'role_nl' => $this->string('role_nl')->trim()->toString(),
            'role_en' => $this->tekst('role_en'),
            'organisation' => $this->string('organisation')->trim()->toString(),
            'organisation_url' => $this->tekst('organisation_url'),
            'employment' => $this->tekst('employment'),
            'workplace' => $this->tekst('workplace'),
            'location_nl' => $this->tekst('location_nl'),
            'location_en' => $this->tekst('location_en'),
            'started_on' => $this->maand('started_on'),
            'ended_on' => $this->maand('ended_on'),
            'description_nl' => $this->tekst('description_nl'),
            'description_en' => $this->tekst('description_en'),
        ];
    }

    /** Heeft de klant deze Engelse tekst door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }

    /** Het geüploade logo, of null als er geen nieuw bestand meekwam. */
    public function logo(): ?UploadedFile
    {
        $bestand = $this->file('logo');

        return $bestand instanceof UploadedFile ? $bestand : null;
    }

    /**
     * Hoe de klant het beeld in het ronde vakje heeft gezet.
     *
     * Kwamen er geen waarden mee, dan valt het terug op "het hele beeld
     * passend, gecentreerd, op wit". Dat is dezelfde stand waarmee de
     * kiezer in het formulier begint.
     */
    public function uitsnede(): Uitsnede
    {
        return new Uitsnede(
            (float) ($this->input('logo_zoom') ?? 1.0),
            (float) ($this->input('logo_x') ?? 0.0),
            (float) ($this->input('logo_y') ?? 0.0),
            $this->boolean('logo_plaat', true),
        );
    }

    /**
     * Wil de klant het bestaande logo weg?
     *
     * Dit is iets anders dan "er kwam geen bestand mee". Bij elke opslag
     * zonder nieuw bestand blijft het oude staan -- anders raak je je logo
     * kwijt zodra je een typefout in de beschrijving verbetert. Weghalen
     * moet je dus expliciet vragen.
     */
    public function wilLogoWeg(): bool
    {
        return $this->boolean('logo_verwijderen');
    }

    /**
     * Een optioneel tekstveld: leeg is null en niet "".
     *
     * Dat onderscheid doet ertoe. De website beslist op `filled()` of een
     * veld getoond wordt, en een lege string is gevuld -- dan krijg je een
     * kopje boven niets.
     */
    private function tekst(string $veld): ?string
    {
        $waarde = $this->string($veld)->trim()->toString();

        return $waarde === '' ? null : $waarde;
    }

    /** "2021-03" wordt 1 maart 2021; de dag betekent niets. */
    private function maand(string $veld): ?Carbon
    {
        $waarde = $this->string($veld)->toString();

        return $waarde === ''
            ? null
            : Carbon::createFromFormat('Y-m-d', $waarde.'-01')->startOfDay();
    }
}
