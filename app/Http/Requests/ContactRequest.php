<?php

namespace App\Http\Requests;

use App\Rules\TurnstileRule;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validatie van het contactformulier.
 *
 * Dit is de plek waar de server het laatste woord heeft. De frontend mag
 * meedenken, maar niets hier is optioneel omdat de browser het al
 * gecontroleerd zou hebben.
 *
 * De honeypot zit niet in deze regels: die wordt afgehandeld door de
 * middleware van spatie/laravel-honeypot, voordat we hier zijn.
 */
class ContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, ValidationRule|string>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', $this->emailRule(), 'max:190'],
            'subject' => ['required', 'string', 'max:160'],
            'message' => ['required', 'string', 'min:10', 'max:5000'],
            'cf-turnstile-response' => ['nullable', 'string', new TurnstileRule],
        ];
    }

    /**
     * De dns-variant controleert of het domein daadwerkelijk mail kan
     * ontvangen. Dat vangt typefouten en wegwerpdomeinen af, maar doet een
     * live DNS-lookup. In de testsuite laten we die weg: anders is elke test
     * afhankelijk van een werkende netwerkverbinding en van DNS-records van
     * domeinen waar wij niets over te zeggen hebben.
     */
    private function emailRule(): string
    {
        return app()->runningUnitTests() ? 'email:rfc' : 'email:rfc,dns';
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'naam',
            'email' => 'e-mailadres',
            'subject' => 'onderwerp',
            'message' => 'bericht',
            'cf-turnstile-response' => 'verificatie',
        ];
    }
}
