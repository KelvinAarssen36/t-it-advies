<?php

namespace App\Providers;

use App\Enums\PageSectionKey;
use App\Enums\SecurityEventType;
use App\Listeners\RecordSecurityEvents;
use App\Models\AboutSetting;
use App\Models\Certificate;
use App\Models\Education;
use App\Models\Experience;
use App\Models\FaqItem;
use App\Models\Service;
use App\Models\Statistic;
use App\Models\User;
use App\Support\Page\SectionContent;
use App\Support\Security\SecurityLogger;
use App\Support\Translation\GeenVertaler;
use App\Support\Translation\MyMemoryVertaler;
use App\Support\Translation\Vertaler;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
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
        // Eén register voor de hele aanvraag, want de tellers worden er in
        // boot() in gezet en moeten er in de controller nog in zitten.
        $this->app->singleton(SectionContent::class);

        /*
         * Staat het vertalen uit, dan komt de lege vertaler in de
         * container. De applicatie heeft dus altijd een vertaler; hij kan
         * alleen niets, en dan verdwijnt de knop uit het scherm. Zie
         * GeenVertaler.
         */
        $this->app->singleton(Vertaler::class, function (): Vertaler {
            if (! config('services.translate.enabled')) {
                return new GeenVertaler;
            }

            return new MyMemoryVertaler(
                true,
                (string) config('services.translate.endpoint'),
                config('services.translate.email'),
                (int) config('services.translate.timeout', 6),
            );
        });
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
        $this->configurePageSections();
        $this->configureTrustedProxies();
    }

    /**
     * Welke proxies we vertrouwen voor het echte IP-adres van een bezoeker.
     *
     * **Dit staat hier en niet in bootstrap/app.php**, en daar is een harde
     * reden voor: die closure draait vóórdat de configuratie is geladen, dus
     * `config()` bestaat daar nog niet. De middleware zelf zit al in de
     * standaardstapel van Laravel; hier wordt alleen de lijst gezet.
     *
     * **Waarom dit er überhaupt is.** Komt er ooit een Cloudflare of een
     * loadbalancer voor de site, dan is `REMOTE_ADDR` het adres van die
     * proxy en niet van de bezoeker. Zonder deze lijst krijgt elke bezoeker
     * dan hetzelfde adres: één regel in het beveiligingslogboek voor
     * iedereen, snelheidsgrenzen die voor alle bezoekers samen gelden, en
     * bezoekcijfers die de hele wereld als één bezoeker per dag zien. Er
     * valt niets om -- er staat alleen overal een verkeerd getal.
     *
     * Leeg is de veilige stand; zie de toelichting in config/security.php.
     */
    private function configureTrustedProxies(): void
    {
        /** @var array<int, string> $proxies */
        $proxies = config('security.trusted_proxies', []);

        if ($proxies !== []) {
            TrustProxies::at($proxies);
        }
    }

    /**
     * Welke onderdelen van de landingspagina hun eigen inhoud tellen.
     *
     * **Dit is de plek waar een nieuwe module zich aanmeldt.** Eén regel per
     * module, en daarmee verdwijnt dat onderdeel vanzelf van de website
     * zolang de klant er nog niets in heeft gezet -- met een uitroepteken op
     * het indelingsscherm, zodat hij weet waaróm hij het niet ziet:
     *
     *     $this->app->make(SectionContent::class)->telt(
     *         PageSectionKey::Timeline,
     *         fn () => TimelineItem::query()->count(),
     *     );
     *
     * De tellers zijn bewust functies en geen getallen: ze mogen alleen
     * draaien als er echt naar gevraagd wordt, en niet bij elke aanvraag
     * die met deze pagina niets te maken heeft.
     *
     * Onderdelen waarvan de tekst in de code staat -- de kop, de voettekst
     * -- melden zich niet aan. Die zijn nooit leeg. Zie
     * docs/architecture/pagina-indeling.md.
     */
    protected function configurePageSections(): void
    {
        /*
         * `online()` en niet gewoon `count()`: een tijdlijn waarvan alles
         * offline staat is voor de bezoeker net zo leeg als een tijdlijn
         * zonder ervaringen. Zou hier het totaal staan, dan meldt het
         * indelingsscherm dat het onderdeel gevuld is terwijl er op de
         * website niets verschijnt -- en dan is die melding erger dan geen
         * melding.
         */
        $this->app->make(SectionContent::class)->telt(
            PageSectionKey::Ervaring,
            fn () => Experience::query()->online()->count(),
        );

        // Zelfde verhaal voor de diensten: staat alles offline, dan is
        // het blok voor de bezoeker leeg en hoort het van de site af.
        $this->app->make(SectionContent::class)->telt(
            PageSectionKey::Diensten,
            fn () => Service::query()->online()->count(),
        );

        /*
         * De certificaten tellen allebei de lijsten mee, want ze staan
         * in hetzelfde blok. Een pagina met alleen een opleiding erin is
         * niet leeg -- dan hoort dat blok er gewoon te staan.
         */
        $this->app->make(SectionContent::class)->telt(
            PageSectionKey::Certificaten,
            fn () => Certificate::query()->online()->count()
                + Education::query()->online()->count(),
        );

        // En de statistieken. Zelfde verhaal: staat alles offline, dan
        // is het blok voor de bezoeker leeg.
        $this->app->make(SectionContent::class)->telt(
            PageSectionKey::Statistieken,
            fn () => Statistic::query()->online()->count(),
        );

        /*
         * En de vragen. Hier is leeg ook echt leeg: een vragenlijst
         * zonder vragen is een kop met niets eronder, en dat hoort van
         * de site af tot de eigenaar zijn eerste vraag heeft geschreven.
         */
        $this->app->make(SectionContent::class)->telt(
            PageSectionKey::Faq,
            fn () => FaqItem::query()->online()->count(),
        );

        /*
         * "Over mij" telt zijn **samenvatting** en niets anders.
         *
         * **Niet de punten.** Die staan alleen op de aparte pagina, en zou
         * de teller daarop afgaan, dan valt het hele blok van de voorpagina
         * tot de eigenaar zijn derde bulletje heeft getypt.
         *
         * **En hij rekent met de taal mee.** De samenvatting valt niet
         * terug op het Nederlands -- een Engelse bezoeker die een
         * Nederlandse alinea over de eigenaar krijgt, krijgt iets wat hij
         * niet kan lezen op de plek waar hij vertrouwen moet opbouwen.
         * Staat er geen Engels, dan hoort het blok op de Engelse site weg
         * te vallen, en dan moet deze teller dat ook zeggen.
         */
        $this->app->make(SectionContent::class)->telt(
            PageSectionKey::OverMij,
            fn () => blank(AboutSetting::huidige()->samenvatting()) ? 0 : 1,
        );
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

        /*
         * Automatisch vertalen kost tekens van een maandtegoed. Twintig
         * keer per minuut is ruim voor iemand die zit te werken, en het
         * houdt een knop die per ongeluk in een lus staat tegen voordat het
         * tegoed op is.
         */
        RateLimiter::for('vertalen', fn (Request $request) => Limit::perMinute(20)
            ->by((string) ($request->user()?->getAuthIdentifier() ?? $request->ip()))
            ->response(fn () => $this->tooManyAttempts($request, 'vertalen')));

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
        Gate::define('viewPulse', fn (User $user) => $user->can('manage portal'));
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
