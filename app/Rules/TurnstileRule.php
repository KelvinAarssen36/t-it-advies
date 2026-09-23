<?php

namespace App\Rules;

use App\Enums\SecurityEventType;
use App\Support\Security\SecurityLogger;
use App\Support\Security\Turnstile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validatieregel voor het Turnstile-token dat het formulier meestuurt.
 *
 * Gebruik in een FormRequest:
 *
 *     'cf-turnstile-response' => ['required', new TurnstileRule()],
 *
 * Mislukte pogingen worden gelogd als beveiligingsgebeurtenis, zodat je in
 * het beveiligde gedeelte kunt zien of een formulier onder vuur ligt. Het
 * token zelf gaat nooit mee de log in: het staat op de redactielijst in
 * config/security.php.
 */
class TurnstileRule implements ValidationRule
{
    public function __construct(
        private readonly Turnstile $turnstile = new Turnstile,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = $this->turnstile->verify(
            is_string($value) ? $value : null,
            request()->ip(),
        );

        if ($result->allowed) {
            return;
        }

        app(SecurityLogger::class)->failure(
            SecurityEventType::TurnstileFailed,
            context: [
                'attribute' => $attribute,
                'error_codes' => $result->errorCodes,
                'path' => request()->path(),
            ],
        );

        $fail(__('De verificatie is niet gelukt. Ververs de pagina en probeer het opnieuw.'));
    }
}
