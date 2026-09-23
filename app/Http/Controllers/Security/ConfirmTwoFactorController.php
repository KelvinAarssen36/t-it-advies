<?php

namespace App\Http\Controllers\Security;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Support\Security\SecurityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

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

    public function store(Request $request, TwoFactorAuthenticationProvider $provider): RedirectResponse
    {
        $user = $request->user();

        abort_if($user === null, 403);

        $validated = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $code = trim($validated['code']);

        // Een recovery code van Fortify ziet eruit als "abcdefghij-klmnopqrst".
        // Die weigeren we hier bewust, en we zeggen ook waarom.
        if (! preg_match('/^\d{6}$/', $code)) {
            $this->logger->failure(SecurityEventType::SensitiveActionRecoveryCodeRefused, $user, [
                'reason' => 'not-a-totp-code',
            ]);

            throw ValidationException::withMessages([
                'code' => __('Vul de zescijferige code uit je authenticator in. Recovery codes gelden hier niet.'),
            ]);
        }

        $secret = $user->two_factor_secret;

        // Dezelfde encrypter als Fortify gebruikt bij het opslaan. Gebruik
        // hier geen Crypt-facade rechtstreeks: Fortify kan zijn encrypter
        // vervangen (bijvoorbeeld voor sleutelrotatie) en dan loopt dit uiteen.
        if (! is_string($secret) || ! $provider->verify(Fortify::currentEncrypter()->decrypt($secret), $code)) {
            $this->logger->failure(SecurityEventType::SensitiveActionFailed, $user);

            throw ValidationException::withMessages([
                'code' => __('Deze code klopt niet.'),
            ]);
        }

        $request->session()->put(
            (string) config('security.sensitive_actions.session_key'),
            time(),
        );

        $this->logger->success(SecurityEventType::SensitiveActionConfirmed, $user);

        return redirect()->intended(route('dashboard'));
    }
}
