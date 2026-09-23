<?php

namespace App\Providers;

use App\Enums\SecurityEventType;
use App\Listeners\RecordSecurityEvents;
use App\Models\User;
use App\Support\Security\SecurityLogger;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\HttpFoundation\Response;

class AppServiceProvider extends ServiceProvider
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
        $this->configureDefaults();
        $this->configureEvents();
        $this->configureRateLimiting();
        $this->configureAuthorization();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        // Achter een load balancer of proxy genereert Laravel anders http-links
        // in mails en redirects, ook al praat de bezoeker via https.
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Koppel de logging aan auth-, Fortify- en mailevents.
     */
    protected function configureEvents(): void
    {
        // RecordOutgoingMail wordt NIET hier geregistreerd: Laravel ontdekt
        // listeners in app/Listeners automatisch aan de hand van een methode
        // die met "handle" begint. Zou je hem hier ook nog aanmelden, dan
        // draait hij twee keer en krijg je dubbele regels in mail_logs.
        //
        // De methoden van RecordSecurityEvents heten daarom bewust record*
        // in plaats van handle*: die ontsnappen aan de automatische ontdekking,
        // zodat subscribe() hieronder de enige registratie is.
        Event::subscribe(RecordSecurityEvents::class);
    }

    /**
     * Rate limiting voor de plekken waar het misbruik vandaan komt.
     *
     * De limieten voor login en de 2FA-challenge staan in
     * FortifyServiceProvider, omdat Fortify die routes registreert.
     */
    protected function configureRateLimiting(): void
    {
        // Gevoelige acties: per gebruiker, niet per IP. Een aanvaller die
        // meerdere IP-adressen heeft, krijgt daar hier niets voor terug.
        RateLimiter::for('sensitive-action', fn (Request $request) => Limit::perMinute(5)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(fn () => $this->tooManyAttempts($request, 'sensitive-action')));

        RateLimiter::for('contact', fn (Request $request) => [
            Limit::perMinute(3)->by((string) $request->ip())
                ->response(fn () => $this->tooManyAttempts($request, 'contact')),
            Limit::perDay(20)->by((string) $request->ip()),
        ]);

        RateLimiter::for('webhook', fn (Request $request) => Limit::perMinute(120)->by((string) $request->ip()));
    }

    /**
     * Wie mag het beveiligde gedeelte in.
     *
     * De rechten zelf komen uit spatie/laravel-permission; hier zetten we
     * alleen de gate die Pulse gebruikt, want die kent dat pakket niet.
     */
    protected function configureAuthorization(): void
    {
        Gate::define('viewPulse', fn (User $user) => $user->can('view pulse'));
    }

    private function tooManyAttempts(Request $request, string $limiter): Response
    {
        app(SecurityLogger::class)->failure(SecurityEventType::RateLimited, $request->user(), [
            'limiter' => $limiter,
            'path' => $request->path(),
        ]);

        return response(__('Te veel pogingen. Probeer het over een minuut opnieuw.'), 429);
    }
}
