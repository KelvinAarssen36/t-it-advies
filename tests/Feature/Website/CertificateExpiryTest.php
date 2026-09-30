<?php

namespace Tests\Feature\Website;

use App\Models\Certificate;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Wat er gebeurt als de geldigheid van een certificaat afloopt.
 *
 * Twee dingen, en ze horen bij elkaar:
 *
 * 1. **Verlopen haalt niets van de website.** Behaald is behaald.
 * 2. **De website zegt ook nergens dát het verlopen is.** Een bezoeker
 *    kijkt naar een etalage en hoeft niet te weten dat één papiertje
 *    aan vernieuwing toe is. Dat betekent: geen label op de tegel, en
 *    ook geen "geldig tot" met een datum van vorig jaar erachter -- dat
 *    is dezelfde mededeling in andere woorden.
 *
 * In het beheerscherm staat het nadrukkelijk wél; daar is het iets om
 * over te beslissen. Zie CertificatePublishingTest voor dat scherm.
 *
 * Dit zijn keuzes die stilletjes kunnen omslaan: iemand die "verlopen"
 * in de database ziet staan, zet er zo weer een label bij. Vandaar dat
 * het hier vastligt.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
class CertificateExpiryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    /** De certificaten zoals de website ze meestuurt. */
    private function opDeSite(): array
    {
        $rijen = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$rijen) {
            $rijen = $page->toArray()['props']['certificates'];
        });

        return $rijen;
    }

    public function test_an_expired_certificate_stays_on_the_website(): void
    {
        Certificate::factory()->verlopen()->create(['title_nl' => 'Oud']);

        $rijen = $this->opDeSite();

        $this->assertCount(1, $rijen);
        $this->assertSame('Oud', $rijen[0]['naam']);
    }

    /**
     * En de website verraadt niet dát het verlopen is.
     *
     * Geen vlag om een label aan op te hangen, en geen datum die het
     * indirect zegt. De server stuurt het gewoon niet mee.
     */
    public function test_the_website_never_says_a_certificate_expired(): void
    {
        Certificate::factory()->verlopen()->create();

        $rij = $this->opDeSite()[0];

        $this->assertArrayNotHasKey('verlopen', $rij);
        $this->assertNull($rij['geldigTot']);
    }

    /** Een geldigheid die nog moet komen mag er wél staan. */
    public function test_a_certificate_that_is_still_valid_shows_its_date(): void
    {
        Certificate::factory()->nogGeldig()->create();

        $this->assertNotNull($this->opDeSite()[0]['geldigTot']);
    }

    /**
     * Zonder vervaldatum is er niets te tonen.
     *
     * Lang niet elk certificaat kent er een. Dat `null` hier hetzelfde
     * oplevert als bij een verlopen certificaat is geen toeval maar de
     * bedoeling: de bezoeker ziet het verschil niet.
     */
    public function test_a_certificate_without_an_expiry_shows_nothing(): void
    {
        Certificate::factory()->create(['expires_on' => null]);

        $this->assertNull($this->opDeSite()[0]['geldigTot']);
    }

    /**
     * De berekening zelf, op het model.
     *
     * Die staat daar en niet in Vue, omdat er twee plekken op leunen:
     * het beheerscherm toont hem, en de website gebruikt hem juist om
     * de geldigheidsdatum wég te laten. Zouden die twee elk hun eigen
     * som doen, dan lopen ze op een dag uiteen.
     */
    public function test_the_model_knows_when_something_expired(): void
    {
        $this->assertTrue(Certificate::factory()->verlopen()->make()->verlopen());
        $this->assertFalse(Certificate::factory()->nogGeldig()->make()->verlopen());
        $this->assertFalse(Certificate::factory()->make(['expires_on' => null])->verlopen());
    }

    /** De maand waarin het afloopt komt als maand op de site, niet als datum. */
    public function test_the_expiry_month_reaches_the_website(): void
    {
        Certificate::factory()->create([
            'issued_on' => now()->subYear()->startOfMonth(),
            'expires_on' => '2030-06-01',
        ]);

        // Met het puntje: Carbon zet er in het Nederlands zelf een
        // afkortingspunt achter. Zie App\Support\Datum.
        $this->assertSame('jun. 2030', $this->opDeSite()[0]['geldigTot']);
    }

    /**
     * In het beheerscherm staat het juist wél.
     *
     * Dit is de andere helft van de afspraak: op de website niets, in
     * het portaal nadrukkelijk. Zou dat ook wegvallen, dan weet de
     * eigenaar niet meer welk papiertje aan vernieuwing toe is.
     */
    public function test_the_management_screen_does_flag_it(): void
    {
        Certificate::factory()->verlopen()->create();

        $beheerder = User::factory()->create();
        $beheerder->givePermissionTo('manage portal');

        $this->actingAs($beheerder)
            ->get(route('website.certificaten.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $rij = $page->toArray()['props']['items'][0];

                $this->assertTrue($rij['verlopen']);
                $this->assertNotNull($rij['geldig_tot']);
            });
    }

    public function test_the_visitor_gets_the_month_in_their_language(): void
    {
        Certificate::factory()->create(['issued_on' => '2024-03-01']);

        $behaald = null;

        $this->withSession(['locale' => 'en'])
            ->get('/')
            ->assertInertia(function (AssertableInertia $page) use (&$behaald) {
                $behaald = $page->toArray()['props']['certificates'][0]['behaald'];
            });

        $this->assertSame('Mar 2024', $behaald);
    }
}
