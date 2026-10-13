<?php

namespace App\Http\Middleware;

use App\Enums\SecurityEventType;
use App\Support\Security\SecurityLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dwingt af dat er kort geleden een authenticator-code is ingevoerd.
 *
 * Bedoeld voor gevoelige acties: het feit dat iemand is ingelogd is daar
 * niet genoeg. Een gestolen sessie komt hier niet langs, want die heeft de
 * authenticator niet.
 *
 * Gebruik als route-middleware:
 *
 *     Route::delete('admin/users/{user}', ...)->middleware('2fa.confirm');
 *
 * Twee harde regels:
 *
 * - Heeft de gebruiker geen bevestigde 2FA, dan gaat de actie niet door. We
 *   sturen naar de beveiligingsinstellingen in plaats van de actie stilletjes
 *   toe te staan.
 * - Recovery codes tellen hier niet. Die logica zit in de controller die de
 *   code controleert; zie ConfirmTwoFactorController.
 */
class RequireTwoFactorConfirmation
{
    public function __construct(private readonly SecurityLogger $logger) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login'));
        }

        if (! $user->hasEnabledTwoFactorAuthentication() || $user->two_factor_confirmed_at === null) {
            $this->logger->failure(SecurityEventType::SensitiveActionDenied, $user, [
                'reason' => 'two-factor-not-enabled',
                'path' => $request->path(),
            ]);

            return redirect()
                ->route('security.edit')
                ->with('status', __('Zet eerst tweestapsverificatie aan. Deze actie vereist een authenticator-code.'));
        }

        if ($this->recentlyConfirmed($request)) {
            return $next($request);
        }

        // Na het bevestigen gaat de gebruiker met een GET naar de onthouden
        // URL. Voor een DELETE of PUT bestaat die GET niet -- dan zou hij na
        // het invoeren van zijn code op een 405 landen. We onthouden in dat
        // geval de pagina waar hij vandaan kwam: daar staat de knop, en de
        // bevestiging is dan vers genoeg om hem meteen nog eens in te drukken.
        $request->session()->put('url.intended', $request->isMethodSafe()
            ? $request->fullUrl()
            : url()->previous());

        return redirect()->route('security.two-factor.confirm');
    }

    private function recentlyConfirmed(Request $request): bool
    {
        $confirmedAt = $request->session()->get(
            (string) config('security.sensitive_actions.session_key'),
        );

        if (! is_int($confirmedAt)) {
            return false;
        }

        $ttl = (int) config('security.sensitive_actions.confirmation_ttl');

        return (time() - $confirmedAt) < $ttl;
    }
}
