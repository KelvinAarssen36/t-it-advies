<?php

namespace App\Http\Requests\Website;

use App\Enums\ProjectType;
use App\Http\Requests\Website\Concerns\LogoVelden;
use App\Http\Requests\Website\Concerns\SchoneVelden;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * De invoer van één project.
 *
 * **Het eigen type is afhankelijk en wordt ook zo gevalideerd.** Kiest
 * de eigenaar "Anders", dan is zijn eigen Nederlandse label verplicht
 * -- anders staat er letterlijk "Anders" op de badge van zijn website.
 * Kiest hij daarna weer een gewoon soort, dan worden allebei de
 * labelvelden op `null` gezet: een weesveld dat nergens meer wordt
 * getoond maar wel in de database staat, komt vroeg of laat ergens
 * terug waar je het niet verwacht. Zie `gegevens()`.
 *
 * **De periode is maand en jaar, geen dag.** Hetzelfde patroon als bij
 * Ervaring: het formulier stuurt "2021-03", `SchoneVelden::maand()`
 * maakt er de eerste van die maand van, en `after_or_equal` houdt tegen
 * dat een project eindigt voordat het begon. Een leeg einde betekent
 * "loopt nog" en is dus een geldig antwoord.
 *
 * **De beeldvelden heten `beeld_*` en niet `logo_*`**; `LogoVelden`
 * laat de veldnaam en de configuratiesleutel overschrijven, want een
 * projectbeeld heeft andere grenzen dan een logo.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal`
 * staat op de hele groep in routes/website.php.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
class ProjectRequest extends FormRequest
{
    use LogoVelden;
    use SchoneVelden;

    /** De langste samenvatting die nog op een kaart past. */
    public const SAMENVATTING_MAX = 300;

    /** En de grens op de twee lange teksten. */
    public const TEKST_MAX = 4000;

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
            /*
             * Of het project op de website staat. Een nieuw project
             * krijgt de keuze in het bevestigingsvenster mee; bij het
             * wijzigen komt hij terug zoals hij stond.
             */
            'published' => ['boolean'],

            'type' => ['required', Rule::enum(ProjectType::class)],

            /*
             * Alleen verplicht bij "Anders", en daar hangt de hele
             * flexibiliteit van deze module aan: zonder eigen label zou
             * de badge het woord "Anders" tonen.
             */
            'type_label_nl' => ['nullable', 'string', 'max:60', 'required_if:type,'.ProjectType::Anders->value],
            'type_label_en' => ['nullable', 'string', 'max:60'],

            'title_nl' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],

            // Een eigennaam, dus maar één keer. Zie de migratie.
            'organisation' => ['required', 'string', 'max:120'],

            'role_nl' => ['required', 'string', 'max:120'],
            'role_en' => ['nullable', 'string', 'max:120'],

            'started_on' => ['required', 'date_format:Y-m'],

            /*
             * Leeg betekent "loopt nog", en dat is een geldig antwoord.
             * `after_or_equal` vangt de omgekeerde volgorde af: zonder
             * die regel kun je een project invoeren dat eindigde voordat
             * het begon, en dan klopt de duur niet meer.
             */
            'ended_on' => ['nullable', 'date_format:Y-m', 'after_or_equal:started_on'],

            'summary_nl' => ['required', 'string', 'max:'.self::SAMENVATTING_MAX],
            'summary_en' => ['nullable', 'string', 'max:'.self::SAMENVATTING_MAX],

            'body_nl' => ['nullable', 'string', 'max:'.self::TEKST_MAX],
            'body_en' => ['nullable', 'string', 'max:'.self::TEKST_MAX],

            'result_nl' => ['nullable', 'string', 'max:'.self::TEKST_MAX],
            'result_en' => ['nullable', 'string', 'max:'.self::TEKST_MAX],

            // Het bestand, het uitsnijvenster en het weghalen.
            ...$this->logoRegels(),

            /*
             * Zet de browser als de klant op "Vertaal" drukte en daarna
             * niets meer in de Engelse velden wijzigde. Een mededeling
             * dus, en geen instelling.
             */
            'machine_translated' => ['boolean'],
        ];
    }

    /** Het beeld heet `beeld` in het formulier; zie de uitleg bovenaan. */
    protected function beeldVeld(): string
    {
        return 'beeld';
    }

    /** En het heeft zijn eigen grenzen; zie config/media.php. */
    protected function mediaSleutel(): string
    {
        return 'project';
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'beeld' => __('afbeelding'),
            'type' => __('soort'),
            'type_label_nl' => __('eigen soort'),
            'type_label_en' => __('Engelse eigen soort'),
            'title_nl' => __('titel'),
            'title_en' => __('Engelse titel'),
            'organisation' => __('organisatie'),
            'role_nl' => __('rol'),
            'role_en' => __('Engelse rol'),
            'started_on' => __('begin'),
            'ended_on' => __('einde'),
            'summary_nl' => __('samenvatting'),
            'summary_en' => __('Engelse samenvatting'),
            'body_nl' => __('omschrijving'),
            'body_en' => __('Engelse omschrijving'),
            'result_nl' => __('resultaat'),
            'result_en' => __('Engelse resultaat'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ended_on.after_or_equal' => __('Het project kan niet eindigen voordat het begon.'),
            'type_label_nl.required_if' => __('Vul je eigen woord in, anders staat er "Anders" op je website.'),
            ...$this->logoBerichten(),
        ];
    }

    /**
     * De gevalideerde invoer als modelvelden.
     *
     * Het beeld zit hier niet bij: dat gaat door `Logo::bewaar()` en
     * levert een pad op dat de controller erbij zet. Zie ProjectController.
     *
     * @return array<string, mixed>
     */
    public function gegevens(): array
    {
        $soort = ProjectType::from($this->string('type')->toString());

        return [
            /*
             * Standaard aan als het veld er niet bij zit -- dezelfde kant
             * op als de standaardwaarde in de migratie. Een project dat
             * je invoert wil je op je website hebben.
             */
            'published' => $this->boolean('published', true),

            'type' => $soort,

            /*
             * Allebei leeg zodra het soort uit de lijst komt. Zou het
             * oude eigen label blijven staan, dan komt het terug zodra
             * iemand ooit weer "Anders" kiest -- met een woord van een
             * project van twee jaar geleden erin.
             */
            'type_label_nl' => $soort->eigenLabel() ? $this->tekst('type_label_nl') : null,
            'type_label_en' => $soort->eigenLabel() ? $this->tekst('type_label_en') : null,

            'title_nl' => $this->string('title_nl')->trim()->toString(),
            'title_en' => $this->tekst('title_en'),
            'organisation' => $this->string('organisation')->trim()->toString(),
            'role_nl' => $this->string('role_nl')->trim()->toString(),
            'role_en' => $this->tekst('role_en'),

            'started_on' => $this->maand('started_on'),
            'ended_on' => $this->maand('ended_on'),

            'summary_nl' => $this->string('summary_nl')->trim()->toString(),
            'summary_en' => $this->tekst('summary_en'),
            'body_nl' => $this->tekst('body_nl'),
            'body_en' => $this->tekst('body_en'),
            'result_nl' => $this->tekst('result_nl'),
            'result_en' => $this->tekst('result_en'),
        ];
    }

    /** Heeft de eigenaar dit Engels door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }
}
