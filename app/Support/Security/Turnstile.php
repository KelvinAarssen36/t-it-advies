<?php

namespace App\Support\Security;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Controleert een Cloudflare Turnstile-token bij Cloudflare zelf.
 *
 * Twee bewuste keuzes:
 *
 * 1. Zonder secret is Turnstile "niet geconfigureerd". In local en testing
 *    laten we het verkeer dan door, zodat je zonder Cloudflare-account kunt
 *    ontwikkelen. In elke andere omgeving weigeren we juist alles: een
 *    productieformulier zonder botcheck is erger dan een kapot formulier.
 *
 * 2. Valt Cloudflare weg (timeout, netwerkfout), dan weigeren we ook. Fail
 *    closed, want anders is de bescherming met een simpele DoS te omzeilen.
 *
 * Zie docs/security/spam-en-botbescherming.md.
 */
class Turnstile
{
    public function isConfigured(): bool
    {
        return filled(config('services.turnstile.secret'));
    }

    /**
     * Mag de check worden overgeslagen omdat er geen Turnstile is ingesteld?
     */
    public function isOptional(): bool
    {
        return ! $this->isConfigured()
            && app()->environment(['local', 'testing']);
    }

    public function verify(?string $token, ?string $ip = null): TurnstileResult
    {
        if ($this->isOptional()) {
            return TurnstileResult::skipped();
        }

        if (! $this->isConfigured()) {
            return TurnstileResult::failed(['configuration-missing']);
        }

        if (blank($token)) {
            return TurnstileResult::failed(['missing-input-response']);
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.turnstile.timeout', 5))
                ->post((string) config('services.turnstile.verify_url'), array_filter([
                    'secret' => config('services.turnstile.secret'),
                    'response' => $token,
                    'remoteip' => $ip,
                ]));
        } catch (ConnectionException) {
            return TurnstileResult::failed(['connection-failed']);
        }

        if ($response->failed()) {
            return TurnstileResult::failed(['provider-error-'.$response->status()]);
        }

        /** @var array{success?: bool, 'error-codes'?: array<int, string>} $body */
        $body = $response->json() ?? [];

        return ($body['success'] ?? false) === true
            ? TurnstileResult::passed()
            : TurnstileResult::failed($body['error-codes'] ?? ['unknown']);
    }
}
