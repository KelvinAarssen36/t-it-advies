<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * De afspraken van de welkomstanimatie, vastgelegd in de bron.
 *
 * De animatie draait alleen in de browser, dus een verzoektest ziet hem
 * nooit. Wat hier staat zijn de regels die hem onschadelijk houden: valt er
 * één weg, dan kan de entree de pagina tegenhouden of bij elke verversing
 * opnieuw spelen, en dat merk je pas als een bezoeker erover klaagt. Het
 * gedrag zelf is in de browser nagelopen; zie "De intro bij binnenkomst" in
 * docs/architecture/frontend-en-animatie.md.
 */
class WelkomstanimatieTest extends TestCase
{
    private function bron(string $pad): string
    {
        return (string) file_get_contents(resource_path($pad));
    }

    public function test_it_only_plays_once_per_browser_and_not_with_an_anchor(): void
    {
        $beslissing = $this->bron('js/lib/intro.ts');

        // Eén vlag in localStorage, geen sessie: verversen speelt hem niet opnieuw.
        $this->assertStringContainsString('localStorage', $beslissing);
        $this->assertStringNotContainsString('sessionStorage', $beslissing);

        // Een anker in het adres slaat hem over.
        $this->assertStringContainsString('window.location.hash', $beslissing);

        // Minder beweging gevraagd: geen entree.
        $this->assertStringContainsString('prefersReducedMotion()', $beslissing);
    }

    public function test_the_hero_never_waits_forever(): void
    {
        $beslissing = $this->bron('js/lib/intro.ts');
        $hero = $this->bron('js/components/site/sections/HeroSection.vue');

        // De hero begint via `naDeIntro`, en die heeft een vangnet in tijd.
        $this->assertStringContainsString('naDeIntro(', $hero);
        $this->assertMatchesRegularExpression('/setTimeout\(\s*eenmaal,\s*UITERLIJK\s*\)/', $beslissing);
    }

    public function test_the_layer_removes_itself_even_if_the_script_stalls(): void
    {
        $opmaak = $this->bron('css/site/intro.css');

        $this->assertStringContainsString('brand-site-intro-vangnet', $opmaak);
        $this->assertMatchesRegularExpression('/pointer-events:\s*none/', $opmaak);
        $this->assertStringContainsString('prefers-reduced-motion: reduce', $opmaak);
    }

    public function test_it_can_be_skipped_and_lives_only_on_the_public_site(): void
    {
        $intro = $this->bron('js/components/site/SiteIntro.vue');

        foreach (['keydown', 'pointerdown', 'wheel', 'touchstart'] as $gebeurtenis) {
            $this->assertStringContainsString("'{$gebeurtenis}'", $intro);
        }

        // Een echte knop om over te slaan, met vertaalde tekst.
        $this->assertStringContainsString("\$t('Intro overslaan')", $intro);

        // Alleen in de publieke layout, nooit in het portaal.
        $this->assertStringContainsString('SiteIntro', $this->bron('js/layouts/PublicLayout.vue'));
        $this->assertStringNotContainsString('SiteIntro', $this->bron('js/layouts/AppLayout.vue'));
    }
}
