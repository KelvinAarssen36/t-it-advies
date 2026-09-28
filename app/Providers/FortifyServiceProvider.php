<?php

namespace App\Providers;

use App\Actions\Fortify\ResetUserPassword;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse;
use Laravel\Fortify\Contracts\LogoutResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRedirects();
        $this->configureRateLimiting();
    }

    /**
     * Na het inloggen niet rechtstreeks naar het dashboard, maar langs het
     * korte laadscherm.
     *
     * Dat scherm pakt zelf de onthouden bestemming op, dus we laten
     * `url.intended` hier met rust -- `redirect()->intended()` zou hem
     * opmaken en dan kom je alsnog op het dashboard in plaats van waar je
     * heen wilde.
     *
     * Allebei de responses zijn nodig: zonder 2FA loopt het inloggen via
     * LoginResponse, met 2FA via TwoFactorLoginResponse.
     */
    private function configureRedirects(): void
    {
        $naarLaadscherm = function (Request $request) {
            /*
             * Onthouden dat er zojuist is ingelogd, zodat het portaal
             * eenmalig kan begroeten.
             *
             * Dit staat hier en niet op het laadscherm zelf: dat scherm
             * staat ook tussen de website en het portaal, en dan zou je
             * begroet worden telkens als je even op je eigen site hebt
             * gekeken. Inloggen gebeurt één keer; dáár hoort het bij.
             *
             * Een sessiewaarde en geen parameter in de URL: die laatste
             * blijft in de geschiedenis staan en groet je bij elke
             * verversing opnieuw. Zie HandleInertiaRequests::welcome().
             */
            $request->session()->put('portal.welcome', true);

            /*
             * Een verse login telt als wachtwoordbevestiging.
             *
             * Zonder deze regel vraagt Laravel op de beveiligingspagina
             * opnieuw om je wachtwoord, ook als je dertig seconden eerder
             * hebt ingelogd. Dat voelt niet als zorgvuldigheid maar als een
             * fout, en het leert iemand zijn wachtwoord klakkeloos in te
             * tikken zodra het gevraagd wordt -- precies het gedrag dat die
             * bevestiging moest voorkomen.
             *
             * Wat we ervoor inleveren: binnen `auth.password_timeout` (drie
             * uur) na het inloggen gaat de beveiligingspagina open zonder
             * extra vraag. Dat is te overzien, want de handelingen die je
             * écht niet wilt terugdraaien -- rollen wijzigen, een account
             * verwijderen -- zitten achter '2fa.confirm' en vragen sowieso
             * om een verse code uit de authenticator. Zie
             * docs/security/gevoelige-acties.md.
             */
            $request->session()->put('auth.password_confirmed_at', time());

            return $request->wantsJson()
                ? new JsonResponse('', 204)
                : redirect()->route('portal.enter');
        };

        $this->app->instance(LoginResponse::class, new class($naarLaadscherm) implements LoginResponse
        {
            public function __construct(private readonly Closure $respond) {}

            public function toResponse($request)
            {
                return ($this->respond)($request);
            }
        });

        $this->app->instance(TwoFactorLoginResponse::class, new class($naarLaadscherm) implements TwoFactorLoginResponse
        {
            public function __construct(private readonly Closure $respond) {}

            public function toResponse($request)
            {
                return ($this->respond)($request);
            }
        });

        /*
         * Bij het uitloggen gooien we ook de geschiedenis van Inertia weg.
         *
         * Zonder dat blijven de pagina's die je als ingelogde gebruiker hebt
         * bekeken in de geschiedenis van de browser staan, compleet met hun
         * props. Eén keer op de terugknop drukken zet daar dan de oude
         * toestand weer neer -- inclusief de link naar het portaal in de kop
         * van de publieke site.
         *
         * Dat is geen lek van gegevens, want de server geeft niets nieuws
         * vrij, maar het is wel precies wat een bezoeker niet hoort te zien.
         * En op een gedeelde computer is het ronduit verwarrend.
         */
        $this->app->instance(LogoutResponse::class, new class implements LogoutResponse
        {
            public function toResponse($request)
            {
                if ($request->wantsJson()) {
                    return new JsonResponse('', 204);
                }

                Inertia::clearHistory();

                return redirect()->route('home');
            }
        });
    }

    private function configureActions(): void
    {
        // Geen createUsersUsing: registratie staat uit. Een account maak je
        // met `php artisan user:create`. Zie config/fortify.php.
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'status' => $request->session()->get('status'),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
            'passwordRules' => Password::defaults()->toPasswordRulesString(),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('passkeys', function (Request $request) {
            return Limit::perMinute(10)->by(
                ($request->input('credential.id') ?: $request->session()->getId()).'|'.$request->ip(),
            );
        });
    }
}
