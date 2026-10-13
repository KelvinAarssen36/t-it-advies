<?php

namespace App\Http\Controllers;

use App\Support\Page\Sitemap;
use Illuminate\Http\Response;

/**
 * De sitemap van de publieke site.
 *
 * **Geen bestand in `public/` maar een route**, en dat is de hele reden
 * dat hij klopt: welke pagina's bestaan hangt af van wat de eigenaar heeft
 * aangezet, en dat verandert terwijl de site draait. Een `sitemap.xml` die
 * bij de bouw is gegenereerd klopt tot de eerste keer dat hij een
 * onderdeel uitzet -- en dan wijst hij naar een 404 zonder dat iemand het
 * merkt.
 *
 * **Geen `TelBezoek`.** Een crawler is geen bezoeker. Zou deze route mee
 * tellen, dan zakt of stijgt het bezoekcijfer met hoe vaak Google langskomt.
 *
 * Welke adressen erin gaan en waarom er geen `hreflang`, `priority` of
 * `changefreq` in staat: zie App\Support\Page\Sitemap.
 */
class SitemapController extends Controller
{
    public function __invoke(Sitemap $sitemap): Response
    {
        return response()
            ->view('sitemap', ['adressen' => $sitemap->adressen()])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
