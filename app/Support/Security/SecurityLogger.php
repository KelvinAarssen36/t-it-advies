<?php

namespace App\Support\Security;

use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Legt beveiligingspogingen vast, zowel geslaagde als mislukte.
 *
 * Harde regel: er gaat nooit een wachtwoord, TOTP-code, recovery code of
 * secret de opslag in. Alles wat via context binnenkomt gaat eerst door
 * redact(), die op sleutelnaam filtert, ook diep in geneste arrays. De lijst
 * met sleutels staat in config/security.php, zodat je hem kunt uitbreiden
 * zonder deze klasse aan te raken.
 *
 * Zie docs/security/logging.md.
 */
class SecurityLogger
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<array-key, mixed>  $context
     */
    public function success(
        SecurityEventType|string $event,
        ?Authenticatable $user = null,
        array $context = [],
        ?string $email = null,
    ): SecurityEvent {
        return $this->record($event, SecurityOutcome::Success, $user, $context, $email);
    }

    /**
     * @param  array<array-key, mixed>  $context
     */
    public function failure(
        SecurityEventType|string $event,
        ?Authenticatable $user = null,
        array $context = [],
        ?string $email = null,
    ): SecurityEvent {
        return $this->record($event, SecurityOutcome::Failure, $user, $context, $email);
    }

    /**
     * @param  array<array-key, mixed>  $context
     */
    public function record(
        SecurityEventType|string $event,
        SecurityOutcome $outcome,
        ?Authenticatable $user = null,
        array $context = [],
        ?string $email = null,
    ): SecurityEvent {
        $name = $event instanceof SecurityEventType ? $event->value : $event;
        $safeContext = $this->redact($context);

        $securityEvent = SecurityEvent::create([
            'user_id' => $user?->getAuthIdentifier(),
            'email' => $email ?? $this->emailOf($user),
            'event' => $name,
            'outcome' => $outcome,
            'ip_address' => $this->request->ip(),
            'user_agent' => Str::limit((string) $this->request->userAgent(), 500, ''),
            'context' => $safeContext ?: null,
        ]);

        // Ook naar het losse logkanaal, zodat dit meegaat in centrale
        // logverzameling zonder dat je de database hoeft te bevragen.
        Log::channel('security')->log(
            $outcome === SecurityOutcome::Failure ? 'warning' : 'info',
            $name,
            array_filter([
                'outcome' => $outcome->value,
                'user_id' => $securityEvent->user_id,
                'email' => $securityEvent->email,
                'ip' => $securityEvent->ip_address,
                'context' => $safeContext ?: null,
            ], fn ($value) => $value !== null),
        );

        return $securityEvent;
    }

    /**
     * Verwijdert gevoelige sleutels uit een contextarray, op elk niveau.
     *
     * Sleutels worden case-insensitief vergeleken en scheidingstekens worden
     * genormaliseerd, zodat recovery-code, recovery_code en recoveryCode alle
     * drie worden herkend.
     *
     * @param  array<array-key, mixed>  $context
     * @return array<array-key, mixed>
     */
    public function redact(array $context): array
    {
        /** @var array<int, string> $redacted */
        $redacted = config('security.logging.redacted_keys', []);
        $needles = array_map($this->normaliseKey(...), $redacted);

        $result = [];

        foreach ($context as $key => $value) {
            if (is_string($key) && in_array($this->normaliseKey($key), $needles, true)) {
                $result[$key] = '[redacted]';

                continue;
            }

            $result[$key] = is_array($value)
                ? $this->redact($value)
                : $this->limit($value);
        }

        return $result;
    }

    private function normaliseKey(string $key): string
    {
        return Str::lower(preg_replace('/[^a-z0-9]/i', '', $key) ?? $key);
    }

    private function limit(mixed $value): mixed
    {
        return is_string($value) ? Str::limit($value, 1000, '') : $value;
    }

    private function emailOf(?Authenticatable $user): ?string
    {
        return $user instanceof User ? $user->email : null;
    }
}
