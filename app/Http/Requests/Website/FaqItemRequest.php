<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\SchoneVelden;
use Illuminate\Foundation\Http\FormRequest;

/**
 * De invoer van één veelgestelde vraag, voor zowel aanmaken als wijzigen.
 *
 * **De twee lengtes liggen ver uit elkaar, en dat is de hele regel hier.**
 * Een vraag moet dichtgeklapt op één regel passen -- vandaar 160 tekens,
 * net als de kolom. Een antwoord mag alinea's hebben en krijgt 2000: dat
 * is ruim drie flinke alinea's, en daarboven is het geen antwoord meer
 * maar een pagina. Wie meer kwijt wil heeft een dienst nodig, niet een
 * langere vraag.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal` staat
 * op de hele groep in routes/website.php.
 */
class FaqItemRequest extends FormRequest
{
    use SchoneVelden;

    /**
     * De hoogste lengte van een antwoord.
     *
     * **Dit getal staat ook in de browser**, in FaqDialoog.vue: de teller
     * onder het veld rekent met dezelfde grens. Lopen die twee uiteen, dan
     * krijgt de eigenaar een foutmelding op iets wat het scherm net nog
     * goedkeurde.
     */
    public const ANTWOORD_MAX = 2000;

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
             * Of de vraag op de website staat. Een nieuwe krijgt de keuze
             * in het bevestigingsvenster mee; bij het wijzigen komt hij
             * terug zoals hij stond.
             */
            'published' => ['boolean'],

            'question_nl' => ['required', 'string', 'max:160'],
            'question_en' => ['nullable', 'string', 'max:160'],

            'answer_nl' => ['required', 'string', 'max:'.self::ANTWOORD_MAX],
            'answer_en' => ['nullable', 'string', 'max:'.self::ANTWOORD_MAX],

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
            'question_nl' => __('vraag'),
            'question_en' => __('Engelse vraag'),
            'answer_nl' => __('antwoord'),
            'answer_en' => __('Engelse antwoord'),
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
             * 160 tekens bevatten") zegt niet waaróm. Bij deze twee velden
             * is dat juist de uitleg: het een is een regel, het ander een
             * stuk tekst.
             */
            'question_nl.max' => __('Een vraag hoort op één regel te passen. Lukt dat niet, dan hoort de rest in het antwoord.'),
            'question_en.max' => __('Een vraag hoort op één regel te passen. Lukt dat niet, dan hoort de rest in het antwoord.'),
            'answer_nl.max' => __('Dit antwoord is te lang voor een vragenlijst. Hou het bij een paar alinea\'s; is er meer te vertellen, dan is het een dienst of een stuk op je "Over mij"-pagina.'),
            'answer_en.max' => __('Dit antwoord is te lang voor een vragenlijst. Hou het bij een paar alinea\'s; is er meer te vertellen, dan is het een dienst of een stuk op je "Over mij"-pagina.'),
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
            /*
             * Standaard aan als het veld er niet bij zit -- dezelfde keuze
             * als de standaardwaarde in de migratie: iets dat je invoert
             * wil je op je website hebben.
             */
            'published' => $this->boolean('published', true),

            'question_nl' => $this->string('question_nl')->trim()->toString(),
            'question_en' => $this->tekst('question_en'),

            /*
             * Het antwoord wordt wél getrimd aan de buitenkant, maar de
             * witregels binnenin blijven staan: dat zijn de alinea's.
             */
            'answer_nl' => $this->string('answer_nl')->trim()->toString(),
            'answer_en' => $this->tekst('answer_en'),
        ];
    }

    /** Heeft de eigenaar dit Engels door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }
}
