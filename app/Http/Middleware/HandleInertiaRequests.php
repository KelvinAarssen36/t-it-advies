<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Honeypot\Honeypot;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user,
                // De frontend gebruikt dit alleen om menu-items te tonen of te
                // verbergen. De echte controle gebeurt altijd op de server.
                'permissions' => $user?->getAllPermissions()->pluck('name')->all() ?? [],
            ],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
            ],
            // De honeypotvelden wisselen per request, dus die moeten mee met
            // elke paginarespons. Zie resources/js/components/HoneypotFields.vue.
            'honeypot' => fn () => app(Honeypot::class)->toArray(),
            'turnstileSiteKey' => config('services.turnstile.site_key'),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
