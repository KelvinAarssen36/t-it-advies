<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Elke afbeelding waar de landing om vraagt, moet er ook zijn.
 *
 * De secties van de publieke site worden in de browser getekend, dus een
 * gewone verzoektest ziet die `<img>` nooit. En een ontbrekende afbeelding
 * geeft geen foutmelding: hij levert een 404 op die alleen in het
 * netwerktabblad staat, en op het scherm een leeg vlak waar een gezicht
 * hoorde te staan. Precies het soort ding dat je pas merkt als de klant
 * het meldt.
 *
 * Daarom leest deze test de componenten en legt hij elke verwijzing naar
 * `/images/` naast de bestanden in `public/`. Dat vangt twee dingen: een
 * afbeelding die nooit is aangeleverd, en een variant die bij het
 * opruimen is weggegooid terwijl het `srcset` er nog naar wijst.
 *
 * Zie docs/architecture/frontend-en-animatie.md.
 */
class LandingBeeldTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function componenten(): array
    {
        return [
            'hero' => ['components/site/sections/HeroSection.vue'],
            'kop' => ['components/site/SiteHeader.vue'],
            'voet' => ['components/site/SiteFooter.vue'],
            'merk' => ['components/AppLogoIcon.vue'],
        ];
    }

    #[DataProvider('componenten')]
    public function test_every_image_the_landing_asks_for_exists(
        string $component,
    ): void {
        $pad = resource_path('js/'.$component);

        $this->assertFileExists($pad);

        preg_match_all(
            '#/images/[A-Za-z0-9_.-]+\.(?:webp|png|jpg|jpeg|svg|ico)#',
            (string) file_get_contents($pad),
            $treffers,
        );

        $verwijzingen = array_unique($treffers[0]);

        // Een component zonder afbeeldingen is geen fout, maar dan zegt
        // deze test ook niets. Dat hoort te blijken.
        if ($verwijzingen === []) {
            $this->addToAssertionCount(1);

            return;
        }

        foreach ($verwijzingen as $verwijzing) {
            $this->assertFileExists(
                public_path(ltrim($verwijzing, '/')),
                "{$component} vraagt om {$verwijzing}, maar dat bestand staat er niet.",
            );
        }
    }

    public function test_the_portrait_is_served_as_webp_and_not_as_the_original(
    ): void {
        /*
         * Het origineel is een PNG van bijna twee megabyte. Op een
         * landingspagina die bezoekers waarschijnlijk op hun telefoon
         * opzoeken is dat onacceptabel, en het is een makkelijke fout om
         * te maken: het bestand staat in dezelfde map als de varianten.
         */
        $hero = (string) file_get_contents(
            resource_path('js/components/site/sections/HeroSection.vue'),
        );

        $this->assertStringNotContainsString('PersoonFoto.png', $hero);
        $this->assertStringContainsString('/images/persoon-', $hero);
    }
}
