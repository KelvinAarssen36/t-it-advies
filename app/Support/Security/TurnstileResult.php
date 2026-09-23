<?php

namespace App\Support\Security;

/**
 * De uitkomst van een Turnstile-controle.
 *
 * "Skipped" is expliciet iets anders dan "passed": zo kun je in logs en
 * tests zien of er echt een botcheck heeft plaatsgevonden of dat die
 * gewoon niet was ingesteld.
 */
final readonly class TurnstileResult
{
    /**
     * @param  array<int, string>  $errorCodes
     */
    private function __construct(
        public bool $allowed,
        public bool $checked,
        public array $errorCodes = [],
    ) {}

    public static function passed(): self
    {
        return new self(allowed: true, checked: true);
    }

    public static function skipped(): self
    {
        return new self(allowed: true, checked: false);
    }

    /**
     * @param  array<int, string>  $errorCodes
     */
    public static function failed(array $errorCodes = []): self
    {
        return new self(allowed: false, checked: true, errorCodes: $errorCodes);
    }
}
