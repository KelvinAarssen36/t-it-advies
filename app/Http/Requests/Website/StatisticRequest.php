<?php

namespace App\Http\Requests\Website;

use App\Enums\StatisticDisplay;
use App\Http\Requests\Website\Concerns\SchoneVelden;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * De invoer van één statistiek, voor zowel aanmaken als wijzigen.
 *
 * **De grens op de waarde hangt van de weergave af**, en dat is de enige
 * regel hier die niet rechttoe rechtaan is. Een balk of ring tekent een
 * deel van een geheel en komt dus nooit boven de honderd; een teller
 * heeft geen geheel. Die grens staat op de enum -- zie
 * `StatisticDisplay::maximum()` -- zodat het beheerscherm hem ook kan
 * tonen, en hij wordt hier afgedwongen.
 *
 * **Autorisatie zit op de route en niet hier.** `can:manage portal`
 * staat op de hele groep in routes/website.php.
 */
class StatisticRequest extends FormRequest
{
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
            'display' => ['required', Rule::enum(StatisticDisplay::class)],

            /*
             * Of de statistiek op de website staat. Een nieuwe krijgt de
             * keuze in het bevestigingsvenster mee; bij het wijzigen
             * komt hij terug zoals hij stond.
             */
            'published' => ['boolean'],

            'label_nl' => ['required', 'string', 'max:60'],
            'label_en' => ['nullable', 'string', 'max:60'],

            /*
             * De bovengrens komt van de weergave. Is die weergave zelf
             * onzin, dan valt hij hierboven al om en is honderd een
             * veilige terugval -- de strengste van de twee.
             */
            'value' => ['required', 'integer', 'min:0', 'max:'.$this->maximum()],

            // Kort, want dit staat tegen een groot getal aan. Meer dan
            // een paar tekens duwt het getal van zijn plek.
            'prefix' => ['nullable', 'string', 'max:8'],
            'suffix' => ['nullable', 'string', 'max:8'],

            'note_nl' => ['nullable', 'string', 'max:120'],
            'note_en' => ['nullable', 'string', 'max:120'],

            'group_nl' => ['nullable', 'string', 'max:60'],
            'group_en' => ['nullable', 'string', 'max:60'],

            /*
             * Zet de browser als de klant op "Vertaal automatisch"
             * drukte en daarna niets meer in de Engelse velden wijzigde.
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
            'display' => __('weergave'),
            'label_nl' => __('naam'),
            'label_en' => __('Engelse naam'),
            'value' => __('waarde'),
            'prefix' => __('teken ervoor'),
            'suffix' => __('teken erachter'),
            'note_nl' => __('notitie'),
            'note_en' => __('Engelse notitie'),
            'group_nl' => __('groep'),
            'group_en' => __('Engelse groep'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            /*
             * Een eigen melding, want de standaardtekst ("mag niet
             * groter zijn dan 100") zegt niet wáárom. Bij een balk of
             * ring is dat de hele reden: het is een deel van honderd
             * procent.
             */
            'value.max' => $this->weergave()?->isPercentage() === true
                ? __('Een balk of ring loopt tot 100 procent. Wil je een hoger getal, kies dan de weergave "Teller".')
                : __('Dat getal is te groot om nog leesbaar op je website te staan. Hou het onder de :max.', [
                    'max' => $this->maximum(),
                ]),
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
            'display' => $this->string('display')->toString(),

            /*
             * Standaard aan als het veld er niet bij zit -- dezelfde
             * keuze als de standaardwaarde in de migratie: iets dat je
             * invoert wil je op je website hebben.
             */
            'published' => $this->boolean('published', true),
            'label_nl' => $this->string('label_nl')->trim()->toString(),
            'label_en' => $this->tekst('label_en'),
            'value' => $this->integer('value'),
            'prefix' => $this->tekst('prefix'),
            'suffix' => $this->tekst('suffix'),
            'note_nl' => $this->tekst('note_nl'),
            'note_en' => $this->tekst('note_en'),
            'group_nl' => $this->tekst('group_nl'),
            'group_en' => $this->tekst('group_en'),
        ];
    }

    /** Heeft de klant deze Engelse tekst door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }

    /** De gekozen weergave, of null als er onzin binnenkwam. */
    private function weergave(): ?StatisticDisplay
    {
        return StatisticDisplay::tryFrom($this->string('display')->toString());
    }

    /** De hoogste waarde die bij de gekozen weergave past. */
    private function maximum(): int
    {
        return $this->weergave()?->maximum() ?? StatisticDisplay::Balk->maximum();
    }
}
