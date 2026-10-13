<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class HandleAppearance
{
    /**
     * Het thema komt uit een cookie, zodat de server `dark` al op <html> kan
     * zetten voordat er JavaScript draait. Zonder dat zie je bij elke
     * paginalading een flits van het verkeerde thema.
     *
     * De waarde wordt hier tegen een witte lijst gehouden. Hij komt uit een
     * cookie, en dat is iets wat een bezoeker zelf zet; alles wat niet
     * letterlijk 'light' is wordt donker, de huisstijl en de standaard.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        View::share(
            'appearance',
            $request->cookie('appearance') === 'light' ? 'light' : 'dark',
        );

        return $next($request);
    }
}
