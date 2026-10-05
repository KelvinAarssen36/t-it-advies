<?php

namespace App\Http\Requests\Website;

use App\Enums\ContactWeergave;
use App\Http\Requests\Website\Concerns\SchoneVelden;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * De instellingen van de module Contact: de weergave en de bevestigingsmail.
 *
 * Twee vensters schrijven naar dezelfde rij, en ze sturen elk alleen hun
 * eigen velden mee. Vandaar `sometimes`: wat er niet bij zit blijft staan
 * zoals het stond, in plaats van leeggemaakt te worden.
 */
class ContactInstellingRequest extends FormRequest
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
            'display' => ['sometimes', Rule::enum(ContactWeergave::class)],

            'confirmation_subject_nl' => ['sometimes', 'required', 'string', 'max:160'],
            'confirmation_subject_en' => ['sometimes', 'nullable', 'string', 'max:160'],

            /*
             * Tweeduizend tekens. Ruim genoeg voor een nette bevestiging
             * met een alinea uitleg, en kort genoeg om niet in een brief
             * te veranderen die niemand leest.
             */
            'confirmation_body_nl' => ['sometimes', 'required', 'string', 'max:2000'],
            'confirmation_body_en' => ['sometimes', 'nullable', 'string', 'max:2000'],

            'automatisch_vertaald' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'display' => __('weergave'),
            'confirmation_subject_nl' => __('onderwerp van de mail'),
            'confirmation_subject_en' => __('Engelse onderwerp'),
            'confirmation_body_nl' => __('tekst van de mail'),
            'confirmation_body_en' => __('Engelse tekst'),
        ];
    }

    /**
     * Alleen de velden die ook echt zijn meegestuurd.
     *
     * @return array<string, mixed>
     */
    public function gegevens(): array
    {
        $velden = [];

        if ($this->has('display')) {
            $velden['display'] = $this->string('display')->toString();
        }

        if ($this->has('confirmation_subject_nl')) {
            $velden['confirmation_subject_nl'] = $this->string('confirmation_subject_nl')->trim()->toString();
            $velden['confirmation_subject_en'] = $this->tekst('confirmation_subject_en');
            $velden['confirmation_body_nl'] = $this->string('confirmation_body_nl')->trim()->toString();
            $velden['confirmation_body_en'] = $this->tekst('confirmation_body_en');
        }

        return $velden;
    }

    public function automatischVertaald(): bool
    {
        return $this->boolean('automatisch_vertaald');
    }
}
