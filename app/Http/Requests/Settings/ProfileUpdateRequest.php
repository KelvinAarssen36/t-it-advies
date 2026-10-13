<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Het profielformulier: alleen de naam.
 *
 * **Het e-mailadres zit hier niet meer bij**, en dat is de kern van de
 * wijziging. Het stond gewoon tussen de velden, dus één verkeerde
 * toetsaanslag maakte van het inlogadres van het portaal een adres dat
 * niet bestaat -- zonder wachtwoord, zonder code, zonder bevestiging en
 * zonder weg terug.
 *
 * Het heeft nu zijn eigen stroom met drie sloten ervoor en een weg terug
 * erna; zie EmailChangeController.
 */
class ProfileUpdateRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return ['name' => $this->nameRules()];
    }
}
