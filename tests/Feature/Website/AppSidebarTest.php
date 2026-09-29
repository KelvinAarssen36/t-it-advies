<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use Tests\TestCase;

/**
 * Elke module hoort zijn eigen regel in de zijbalk te hebben.
 *
 * Dat is een afspraak die je makkelijk vergeet: je bouwt een module, je
 * meldt hem aan in het sectieregister, hij verschijnt keurig op de
 * indelingspagina -- en dan staat hij nergens in het menu. De eigenaar
 * moet er dan elke keer via een omweg naartoe.
 *
 * Deze test leest het bestand en niet de gerenderde pagina. Dat is grover
 * dan een echte schermtest, maar het is een controle die niet kan
 * verlopen: zodra `beheerRoute()` een route teruggeeft, moet die route ook
 * in de zijbalk staan.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class AppSidebarTest extends TestCase
{
    public function test_every_manageable_section_has_its_own_entry(): void
    {
        $zijbalk = (string) file_get_contents(
            resource_path('js/components/AppSidebar.vue'),
        );

        $gevonden = 0;

        foreach (PageSectionKey::cases() as $sectie) {
            $route = $sectie->beheerRoute();

            if ($route === null) {
                continue;
            }

            $gevonden++;

            /*
             * De naam van de Wayfinder-module, afgeleid van de routenaam:
             * `website.ervaring.index` wordt `ervaring.index()`. Dat is
             * precies zoals de zijbalk hem aanroept.
             */
            $delen = explode('.', $route);
            $aanroep = $delen[count($delen) - 2].'.'.end($delen).'()';

            $this->assertStringContainsString(
                $aanroep,
                $zijbalk,
                "Module [{$sectie->value}] heeft een beheerscherm maar staat niet in de zijbalk. "
                    .'Zet hem in websiteItems in AppSidebar.vue.',
            );
        }

        // Eerst bewijzen dat er iets te controleren viel: zonder deze
        // regel slaagt de test ook als er geen enkele module bestaat.
        $this->assertGreaterThan(0, $gevonden);
    }
}
