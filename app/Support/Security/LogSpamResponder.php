<?php

namespace App\Support\Security;

use App\Enums\SecurityEventType;
use Closure;
use Illuminate\Http\Request;
use Spatie\Honeypot\SpamResponder\SpamResponder;
use Symfony\Component\HttpFoundation\Response;

/**
 * Wat er gebeurt als de honeypot aanslaat.
 *
 * De standaardresponder van het package geeft een lege pagina terug. Dat
 * werkt slecht in een Inertia-app en vertelt een bot bovendien dat hij
 * betrapt is. Wij loggen de poging en doen alsof het gelukt is: de bot
 * verspilt zijn tijd en wij zien het terug in het beveiligde gedeelte.
 *
 * Zie docs/security/spam-en-botbescherming.md.
 */
class LogSpamResponder implements SpamResponder
{
    public function __construct(private readonly SecurityLogger $logger) {}

    public function respond(Request $request, Closure $next): Response
    {
        $this->logger->failure(SecurityEventType::SpamBlocked, context: [
            'path' => $request->path(),
            'method' => $request->method(),
            'fields' => array_keys($request->all()),
        ]);

        return back()->with('status', __('Bedankt voor je bericht. We nemen snel contact op.'));
    }
}
