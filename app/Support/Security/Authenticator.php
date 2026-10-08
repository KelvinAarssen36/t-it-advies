<?php

namespace App\Support\Security;

use App\Enums\SecurityEventType;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\TwoFactorAuthenticationProvider;
use Laravel\Fortify\Fortify;

/**
 * Eén plek die een verse authenticator-code controleert.
 *
 * Er zijn twee manieren waarop dit portaal om zo'n code vraagt, en ze
 * horen allebei hetzelfde te doen:
 *
 * - **Een apart scherm**, via de middleware `2fa.confirm`. Dat werkt voor
 *   een knop die meteen iets doet -- een rij verwijderen bijvoorbeeld.
 * - **Een veld in het venster zelf**, voor een handeling die je eerst
 *   invult. Daar werkt de middleware niet: hij kan een POST of PUT niet
 *   onthouden, dus na het invoeren van de code is het verzoek weg en moet
 *   je alles opnieuw doen.
 *
 * Zonder deze klasse zou de tweede manier de regels van de eerste
 * overschrijven, en dan gaan ze uiteen zodra er één verandert.
 *
 * **Recovery codes gelden hier niet**, in allebei de gevallen. Je bent al
 * ingelogd; een recovery code bewijst niet dat je de authenticator nog
 * hebt, en dat is juist wat een gevoelige actie wil weten. Bij het
 * inloggen ligt dat andersom -- zie docs/security/gevoelige-acties.md.
 */
class Authenticator
{
    public function __construct(
        private readonly TwoFactorAuthenticationProvider $provider,
        private readonly SecurityLogger $logboek,
        private readonly Request $request,
    ) {}

    /**
     * De code controleren en de bevestiging in de sessie zetten.
     *
     * Slaagt hij, dan telt de gebruiker het komende kwartier als bevestigd
     * -- net als na het aparte scherm. Dat is geen bijvangst maar het
     * punt: of je de code nu in een venster of op dat scherm invult, de
     * uitkomst hoort dezelfde te zijn.
     *
     * @param  string  $veld  de naam waaronder een fout terugkomt in het formulier
     *
     * @throws ValidationException
     */
    public function bevestig(User $gebruiker, string $ingevoerd, string $veld = 'code'): void
    {
        $code = trim($ingevoerd);

        // Een recovery code van Fortify ziet eruit als
        // "abcdefghij-klmnopqrst". Die weigeren we, en we zeggen waarom.
        if (preg_match('/^\d{6}$/', $code) !== 1) {
            $this->logboek->failure(SecurityEventType::SensitiveActionRecoveryCodeRefused, $gebruiker, [
                'reason' => 'not-a-totp-code',
            ]);

            throw ValidationException::withMessages([
                $veld => __('Vul de zescijferige code uit je authenticator in. Recovery codes gelden hier niet.'),
            ]);
        }

        $secret = $gebruiker->two_factor_secret;

        /*
         * Dezelfde encrypter als Fortify gebruikt bij het opslaan. Gebruik
         * hier geen Crypt-facade rechtstreeks: Fortify kan zijn encrypter
         * vervangen (bijvoorbeeld voor sleutelrotatie) en dan loopt dit
         * uiteen.
         */
        if (! is_string($secret) || ! $this->provider->verify(Fortify::currentEncrypter()->decrypt($secret), $code)) {
            $this->logboek->failure(SecurityEventType::SensitiveActionFailed, $gebruiker);

            throw ValidationException::withMessages([
                $veld => __('Deze code klopt niet.'),
            ]);
        }

        $this->request->session()->put(
            (string) config('security.sensitive_actions.session_key'),
            time(),
        );

        $this->logboek->success(SecurityEventType::SensitiveActionConfirmed, $gebruiker);
    }

    /**
     * Of er kort geleden al een code is ingevoerd.
     *
     * Hetzelfde sommetje als in RequireTwoFactorConfirmation, en met opzet
     * hier ook: een scherm dat een codeveld toont terwijl de bevestiging
     * nog vers is, vraagt iets wat het al heeft.
     */
    public function isNogVers(): bool
    {
        $bevestigdOp = $this->request->session()->get(
            (string) config('security.sensitive_actions.session_key'),
        );

        if (! is_int($bevestigdOp)) {
            return false;
        }

        return (time() - $bevestigdOp) < (int) config('security.sensitive_actions.confirmation_ttl');
    }
}
