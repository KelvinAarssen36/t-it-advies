<?php

namespace App\Http\Requests\Website;

use App\Enums\EmploymentType;
use App\Enums\ExperienceIcon;
use App\Enums\WorkplaceType;
use App\Http\Requests\Website\Concerns\LogoVelden;
use App\Http\Requests\Website\Concerns\SchoneVelden;
use Illuminate\Foundation\Http\FormRequest;
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
    use LogoVelden;
    use SchoneVelden;

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

            // Het logo van de organisatie, met het uitsnijvenster
            // erachter. Zie de trait LogoVelden.
            ...$this->logoRegels(),

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
            ...$this->logoBerichten(),
        ];
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
}
