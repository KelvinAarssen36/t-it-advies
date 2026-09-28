<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De koppen die de browser vertellen wat hij níet mag doen.
 *
 * Dit is beveiliging die je gratis van de browser krijgt, mits je erom
 * vraagt -- en het is precies het soort ding dat bij een herinrichting van
 * de middleware ongemerkt verdwijnt. Vandaar deze test.
 *
 * Zie docs/security/headers.md.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_headers_are_on_a_normal_page(): void
    {
        $this->get(route('home'))
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeader('Permissions-Policy');
    }

    public function test_they_are_also_on_an_error_page(): void
    {
        // Juist daar: een foutpagina valt buiten de gewone stroom, en dat
        // is precies waar zulke koppen wegvallen als je ze op de webgroep
        // zet in plaats van globaal.
        $this->get('/bestaat-echt-niet')
            ->assertNotFound()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_hsts_stays_away_from_plain_http(): void
    {
        /*
         * Zou hij er wel staan, dan onthoudt je browser twee jaar lang dat
         * *.test alleen via https mag -- en dan kom je er lokaal niet meer
         * bij zonder je browsergegevens te wissen. Een middag zoeken.
         */
        $this->get(route('home'))
            ->assertHeaderMissing('Strict-Transport-Security');
    }
}
