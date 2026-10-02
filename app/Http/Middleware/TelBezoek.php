<?php

namespace App\Http\Middleware;

use App\Support\Bezoek\Bezoekteller;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Telt een bezoek aan de publieke site.
 *
 * **Hij telt ná het antwoord en niet ervoor.** Dat is geen detail: wat de
 * bezoeker ziet hoort niet te wachten op onze boekhouding, en een pagina
 * die niet gelukt is hoort niet als bezoek te tellen.
 *
 * **En hij eet zijn eigen fouten op.** Gaat er in de teller iets mis -- een
 * database die even niet wil, een tabel die na een halve deploy nog niet
 * bestaat -- dan merkt de bezoeker daar niets van. Dat is een bewuste
 * afweging: een gemist bezoekcijfer is te overzien, een landingspagina die
 * omvalt omdat de teller struikelde niet. De fout gaat wel naar de
 * crashmelder langs de gewone weg, dus hij verdwijnt niet stil.
 *
 * Zet deze middleware op de routes van de publieke site en niet globaal.
 * Het portaal, de webhooks en de gezondheidscontrole zijn geen bezoek.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
class TelBezoek
{
    public function __construct(private readonly Bezoekteller $teller) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        /*
         * Alleen een gelukte pagina. Een 404, een doorverwijzing na het
         * wisselen van taal of een 500 is geen bezoek aan de site.
         */
        if ($response->getStatusCode() !== 200) {
            return $response;
        }

        try {
            $this->teller->tel($request);
        } catch (Throwable $fout) {
            report($fout);
        }

        return $response;
    }
}
