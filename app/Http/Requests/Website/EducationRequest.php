<?php

namespace App\Http\Requests\Website;

use App\Http\Requests\Website\Concerns\SchoneVelden;
use Illuminate\Foundation\Http\FormRequest;

/**
 * De invoer van één opleiding.
 *
 * Kort, en dat is het ontwerp: dit is een lijstje van twee of drie
 * regels onder de certificaten. Geen logo, geen nummer, geen lang
 * verhaal.
 *
 * **Autorisatie zit op de route en niet hier.** Zie routes/website.php.
 */
class EducationRequest extends FormRequest
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
            'published' => ['boolean'],

            'title_nl' => ['required', 'string', 'max:120'],
            'title_en' => ['nullable', 'string', 'max:120'],

            // Een eigennaam, dus maar één keer.
            'institution' => ['required', 'string', 'max:120'],

            // Kort, want het staat rechts op één regel naast de periode.
            'level_nl' => ['nullable', 'string', 'max:60'],
            'level_en' => ['nullable', 'string', 'max:60'],

            'started_on' => ['required', 'date_format:Y-m'],

            /*
             * Leeg betekent "loopt nog", en dat is een geldig antwoord.
             * `after_or_equal` vangt de omgekeerde periode af.
             */
            'ended_on' => ['nullable', 'date_format:Y-m', 'after_or_equal:started_on'],

            'machine_translated' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title_nl' => __('opleiding'),
            'title_en' => __('Engelse opleiding'),
            'institution' => __('instelling'),
            'level_nl' => __('niveau'),
            'level_en' => __('Engels niveau'),
            'started_on' => __('begindatum'),
            'ended_on' => __('einddatum'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ended_on.after_or_equal' => __('De einddatum kan niet vóór de begindatum liggen.'),
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
            'published' => $this->boolean('published', true),
            'title_nl' => $this->string('title_nl')->trim()->toString(),
            'title_en' => $this->tekst('title_en'),
            'institution' => $this->string('institution')->trim()->toString(),
            'level_nl' => $this->tekst('level_nl'),
            'level_en' => $this->tekst('level_en'),
            'started_on' => $this->maand('started_on'),
            'ended_on' => $this->maand('ended_on'),
        ];
    }

    /** Heeft de klant deze Engelse tekst door de vertaaldienst laten maken? */
    public function automatischVertaald(): bool
    {
        return $this->boolean('machine_translated');
    }
}
