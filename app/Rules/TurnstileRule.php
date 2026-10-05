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
 * Gebruik in een FormRequest altijd via `veld()`:
 *
 *     'cf-turnstile-response' => TurnstileRule::veld(),
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

    /**
     * De complete regels voor het tokenveld.
     *
     * **`required` en niet `nullable`, en dit is geen smaakkwestie.**
     * Laravel slaat een regel die niet "impliciet" is over zodra het veld
     * afwezig of leeg is -- zie `presentOrRuleIsImplicit()` in de
     * Validator. Met `nullable` was de hele botcheck dus te omzeilen door
     * het token simpelweg niet mee te sturen: de inzending kwam er zonder
     * één foutmelding door. Een onjuist token werd wél geweigerd, en
     * daardoor zag het er in elke test goed uit.
     *
     * Het veld mág alleen ontbreken wanneer Turnstile zelf niets
     * controleert, en dat is lokaal zonder secret. Dat is exact dezelfde
     * vraag die `verify()` stelt, dus hij wordt hier één keer gesteld en
     * niet nog eens in elke FormRequest.
     *
     * Daarom is dit een methode en geen voorbeeld in een docblock: een
     * voorbeeld om na te typen is precies hoe die `nullable` op meer
     * plekken terecht zou komen.
     *
     * Zie docs/security/spam-en-botbescherming.md.
     *
     * @return array<int, string|self>
     */
    public static function veld(): array
    {
        // Eén keer oplossen en meegeven, zodat de vraag "staat Turnstile
        // aan" en de controle zelf gegarandeerd met dezelfde instelling
        // werken.
        $turnstile = app(Turnstile::class);

        return [
            $turnstile->isOptional() ? 'nullable' : 'required',
            'string',
            new self($turnstile),
        ];
    }

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
