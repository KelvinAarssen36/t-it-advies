<?php

namespace App\Http\Requests\Settings;

use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Het aanvragen van een nieuw inlogadres.
 *
 * **Twee sloten in dit formulier, en een derde op de route.** Hier staan
 * het nieuwe adres en het huidige wachtwoord; de route eist daarnaast een
 * verse authenticator-code (`2fa.confirm`).
 *
 * Waarom het wachtwoord er náást de code staat: die code bewijst dat je
 * je telefoon hebt, niet dat jíj achter dit scherm zit. Een open sessie op
 * een onbeheerde laptop komt langs de code als die vijftien minuten
 * geleden is ingetypt. Het wachtwoord sluit dat af.
 *
 * Zie docs/security/inlogadres-wijzigen.md.
 */
class EmailChangeRequest extends FormRequest
{
    use ProfileValidationRules;

    /**
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    public function rules(): array
    {
        return [
            'email' => [
                ...$this->emailRules($this->user()?->id),

                /*
                 * Hetzelfde adres opnieuw invoeren is geen wijziging.
                 * Zonder deze regel stuurt het portaal twee mails en zet
                 * het een aanvraag klaar die niets doet.
                 */
                Rule::notIn([$this->user()?->email]),
            ],

            'password' => ['required', 'string', 'current_password'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email' => __('nieuw e-mailadres'),
            'password' => __('huidige wachtwoord'),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'email.not_in' => __('Dat is het adres dat je nu al gebruikt.'),
            'password.current_password' => __('Dat is niet je huidige wachtwoord.'),
        ];
    }

    public function nieuwAdres(): string
    {
        return mb_strtolower($this->string('email')->trim()->toString());
    }
}
