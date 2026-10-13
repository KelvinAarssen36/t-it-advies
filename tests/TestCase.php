<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Elk verzoek in een test komt van een Nederlandse browser.
     *
     * Sinds een bezoeker zonder keuze de taal van zijn browser krijgt
     * (zie App\Http\Middleware\SetLocale) maakt het uit wat een
     * testverzoek meestuurt. Stuurt hij niets, dan is dat "geen
     * Nederlands" en antwoordt de applicatie in het Engels -- en dan
     * struikelt elke test die naar een Nederlandse zin zoekt over iets
     * dat niets met zijn onderwerp te maken heeft.
     *
     * Die tests gaan over wat er op het scherm staat en niet over
     * taalonderhandeling, dus krijgen ze hier de taal waarin dit project
     * is geschreven. Wie de onderhandeling zélf test -- LocaleTest --
     * zet de kop expliciet, of haalt hem met `flushHeaders()` weg.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withHeader('Accept-Language', 'nl-NL,nl;q=0.9');
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
