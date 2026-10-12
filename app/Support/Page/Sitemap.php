<?php

namespace App\Support\Page;

use App\Enums\ContactWeergave;
use App\Http\Controllers\PublicProjectsController;
use App\Http\Controllers\PublicWerkwijzeController;
use App\Models\AboutSetting;
use App\Models\ContactSetting;
use App\Models\Project;
use App\Models\WorkStep;
use App\Support\Contact\Contactformulier;
use Carbon\CarbonInterface;

/**
 * De adressen die een zoekmachine van deze site mag kennen.
 *
 * **Vier van de zes pagina's bestaan alleen als de eigenaar ze heeft
 * aangezet**, en geven anders een 404. Een sitemap die zo'n adres toch
 * noemt stuurt elke crawler naar een dode deur -- en dat is erger dan geen
 * sitemap, want dan ben je niet alleen onvindbaar maar ook onbetrouwbaar.
 *
 * Deze klasse stelt dus per pagina dezelfde vraag als de pagina zelf. Waar
 * de controller een publieke methode heeft (`sectieStaatAan()`,
 * `ietsTeLezen()`) wordt die gebruikt in plaats van een eigen query: twee
 * eigen antwoorden op dezelfde vraag gaan vroeg of laat uiteen lopen.
 *
 * **Wat er bewust níet in staat:**
 *
 * - **Geen `hreflang`.** Deze site heeft geen taal-URL's: de taal komt uit
 *   het profiel, de sessie of de browser, en `/` is in beide talen
 *   hetzelfde adres. Een `hreflang` zou naar adressen wijzen die niet
 *   bestaan.
 * - **Geen `priority` en geen `changefreq`.** Google negeert allebei al
 *   jaren. Ze opnemen betekent een getal verzinnen dat niemand leest.
 * - **`lastmod` alleen waar we het écht weten**, en dat is bij een
 *   project. Voor de voorpagina zou het een optelsom zijn van een stuk of
 *   tien tabellen, en een datum die we gokken is een datum waarop een
 *   crawler ten onrechte vertrouwt.
 *
 * Zie docs/architecture/sitemap.md.
 */
class Sitemap
{
    /**
     * De adressen, in de volgorde waarin ze op de site staan.
     *
     * @return array<int, array{loc: string, lastmod: string|null}>
     */
    public function adressen(): array
    {
        $adressen = [
            $this->regel(route('home')),

            /*
             * De privacyverklaring staat er altijd. Hij hoort erin: het is
             * een echte pagina met echte inhoud, en hij wordt gezocht.
             */
            $this->regel(route('privacy')),
        ];

        if ($this->contactpaginaBestaat()) {
            $adressen[] = $this->regel(route('contact'));
        }

        if ($this->overMijPaginaBestaat()) {
            $adressen[] = $this->regel(route('over-mij'));
        }

        if ($this->werkwijzePaginaBestaat()) {
            $adressen[] = $this->regel(route('werkwijze'));
        }

        if (! PublicProjectsController::sectieStaatAan()) {
            return $adressen;
        }

        $projecten = Project::query()->online()->opVolgorde()->get();

        if ($projecten->isEmpty()) {
            return $adressen;
        }

        $adressen[] = $this->regel(route('projecten'));

        foreach ($projecten as $project) {
            $adressen[] = $this->regel(
                route('project', $project->slug),
                $project->updated_at,
            );
        }

        return $adressen;
    }

    /**
     * Dezelfde twee poorten als PublicContactController.
     *
     * `staatAan()` en geen eigen query: ContactController stelt die vraag
     * ook voordat hij een inzending aanneemt.
     */
    private function contactpaginaBestaat(): bool
    {
        if (ContactSetting::huidige()->display !== ContactWeergave::EigenPagina) {
            return false;
        }

        return app(Contactformulier::class)->staatAan();
    }

    /** Dezelfde twee poorten als PublicAboutController. */
    private function overMijPaginaBestaat(): bool
    {
        $instelling = AboutSetting::huidige();

        return $instelling->paginaStaatKlaar() && $instelling->sectieStaatAan();
    }

    /** Dezelfde drie poorten als PublicWerkwijzeController. */
    private function werkwijzePaginaBestaat(): bool
    {
        if (! PublicWerkwijzeController::sectieStaatAan()) {
            return false;
        }

        $stappen = WorkStep::query()->online()->opVolgorde()->get();

        if ($stappen->isEmpty()) {
            return false;
        }

        return PublicWerkwijzeController::ietsTeLezen($stappen->all());
    }

    /**
     * @return array{loc: string, lastmod: string|null}
     */
    private function regel(string $adres, ?CarbonInterface $gewijzigd = null): array
    {
        return [
            'loc' => $adres,

            // W3C-datum, zoals de sitemapstandaard voorschrijft.
            'lastmod' => $gewijzigd?->toAtomString(),
        ];
    }
}
