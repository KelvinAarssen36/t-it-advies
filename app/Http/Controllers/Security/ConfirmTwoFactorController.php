<?php

namespace App\Http\Controllers\Security;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Support\Security\Authenticator;
use App\Support\Security\SecurityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Vraagt een verse authenticator-code voor een gevoelige actie.
 *
 * Dit staat los van de Fortify-challenge bij het inloggen. Daar mag je een
 * recovery code gebruiken, want dan probeer je juist weer binnen te komen.
 * Hier niet: een recovery code bewijst niet dat je de authenticator nog
 * hebt, en dat is precies wat we bij een gevoelige actie willen weten.
 *
 * Zie docs/security/gevoelige-acties.md.
 */
class ConfirmTwoFactorController extends Controller
{
    public function __construct(private readonly SecurityLogger $logger) {}

    public function show(Request $request): Response
    {
        $this->logger->success(SecurityEventType::SensitiveActionChallenged, $request->user(), [
            'intended' => $request->session()->get('url.intended'),
        ]);

        return Inertia::render('auth/ConfirmTwoFactor', [
            'intended' => $request->session()->get('url.intended'),
        ]);
    }

    /**
     * De ingevoerde code controleren.
     *
     * Het controleren zelf staat in App\Support\Security\Authenticator,
     * want dit is niet de enige plek waar om een verse code wordt
     * gevraagd. Een handeling die je eerst invult -- de schakelaar voor de
     * extra stap na een passkey bijvoorbeeld -- kan dit scherm niet
     * gebruiken: de middleware zou het ingevulde formulier weggooien. Die
     * zet het codeveld in zijn eigen venster, en gebruikt dezelfde klasse.
     */
    public function store(Request $request, Authenticator $authenticator): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $authenticator->bevestig($user, (string) $validated['code']);

        return redirect()->intended(route('dashboard'));
    }
}
