<?php

namespace App\Http\Requests\Website;

use App\Enums\CoreFactIcon;
use App\Http\Requests\Website\Concerns\SchoneVelden;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Enum;

/**
 * De invoer van één kerngegeven, voor zowel aanmaken als wijzigen.
 *
 * **De drie lengtes zijn de vorm van de strook, niet de ruimte in de
 * database.** Een label van zestig tekens loopt over twee regels en breekt
 * de rij; een waarde boven de tachtig is geen feit meer maar een alinea.
 * De toelichting krijgt honderdtwintig: één regel eronder, zoals de
 * notitie onder een statistiek.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal` staat
 * op de hele groep in routes/website.php.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */
class CoreFactRequest extends FormRequest
{
    use SchoneVelden;

    /**
     * De hoogste lengte van een waarde.
     *
     * **Dit getal staat ook in de browser**, in KerngegevenDialoog.vue:
     * de teller onder het veld rekent met dezelfde grens. Lopen die twee
     * uiteen, dan krijgt de eigenaar een foutmelding op iets wat het
     * scherm net nog goedkeurde.
     */
    public const WAARDE_MAX = 80;

    /** En van de toelichting eronder. */
    public const NOTITIE_MAX = 120;

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
            'icon' => ['required', new Enum(CoreFactIcon::class)],

            /*
             * Of het kerngegeven op de website staat. Een nieuw gegeven
             * krijgt de keuze in het bevestigingsvenster mee; bij het
             * wijzigen komt hij terug zoals hij stond.
             */
            'published' => ['boolean'],

            'label_nl' => ['required', 'string', 'max:60'],
            'label_en' => ['nullable', 'string', 'max:60'],

            'value_nl' => ['required', 'string', 'max:'.self::WAARDE_MAX],
            'value_en' => ['nullable', 'string', 'max:'.self::WAARDE_MAX],

            'note_nl' => ['nullable', 'string', 'max:'.self::NOTITIE_MAX],
            'note_en' => ['nullable', 'string', 'max:'.self::NOTITIE_MAX],

            /*
             * Zet de browser als de eigenaar op "Vertaal" drukte en daarna
             * niets meer in de Engelse velden wijzigde.
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
            'icon' => __('soort'),
            'label_nl' => __('label'),
            'label_en' => __('Engelse label'),
            'value_nl' => __('waarde'),
            'value_en' => __('Engelse waarde'),
            'note_nl' => __('toelichting'),
            'note_en' => __('Engelse toelichting'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            /*
             * Eigen meldingen, want de standaardtekst ("mag niet meer dan
             * 80 tekens bevatten") zegt niet waaróm. Hier is dat juist de
             * uitleg: de grens zit in de vorm van de strook.
             */
            'label_nl.max' => __('Een label hoort op één regel te passen. Zet de uitleg in de waarde eronder.'),
            'label_en.max' => __('Een label hoort op één regel te passen. Zet de uitleg in de waarde eronder.'),
            'value_nl.max' => __('Dit is te lang voor een kerngegeven. Hou het bij één zin; is er meer uit te leggen, zet dat dan in de toelichting of maak er een vraag van.'),
            'value_en.max' => __('Dit is te lang voor een kerngegeven. Hou het bij één zin; is er meer uit te leggen, zet dat dan in de toelichting of maak er een vraag van.'),
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
             * Standaard aan als het veld er niet bij zit -- dezelfde keuze
             * als de standaardwaarde in de migratie: iets dat je invoert
             * wil je op je website hebben.
             */
            'published' => $this->boolean('published', true),

            'label_nl' => $this->string('label_nl')->trim()->toString(),
            'label_en' => $this->tekst('label_en'),

            'value_nl' => $this->string('value_nl')->trim()->toString(),
            'value_en' => $this->tekst('value_en'),

            'note_nl' => $this->tekst('note_nl'),
            'note_en' => $this->tekst('note_en'),
        ];
    }

    /** Heeft de eigenaar dit Engels door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }
}
