<?php

namespace Tests\Feature\Website;

use App\Models\Statistic;
use App\Models\User;
use App\Support\Translation\Vertaler;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Hoe de statistieken in groepen worden ingedeeld.
 *
 * **De kern hiervan is één regel die makkelijk om te draaien is:**
 * groeperen gebeurt op het *Nederlandse* veld, ook voor een Engelse
 * bezoeker. Het Engelse veld is alleen het kopje.
 *
 * Zou je op de vertaalde waarde groeperen, dan valt een groep in het
 * Engels uit elkaar zodra één item zijn Engelse groepsnaam mist -- en
 * dan staat dezelfde site in twee talen anders ingedeeld. Dat is precies
 * het soort fout dat niemand ziet tot een bezoeker de taal omzet.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
class StatisticGroupTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    /** De groepen zoals de website ze meestuurt. */
    private function opDeSite(string $taal = 'nl'): array
    {
        $groepen = [];

        $this->withSession(['locale' => $taal])
            ->get('/')
            ->assertInertia(function (AssertableInertia $page) use (&$groepen) {
                $groepen = $page->toArray()['props']['statistics'];
            });

        return $groepen;
    }

    public function test_items_with_the_same_group_end_up_together(): void
    {
        Statistic::factory()->inGroep('Netwerk')->create(['position' => 1]);
        Statistic::factory()->inGroep('Cloud')->create(['position' => 2]);
        Statistic::factory()->inGroep('Netwerk')->create(['position' => 3]);

        $groepen = $this->opDeSite();

        $this->assertCount(2, $groepen);
        $this->assertSame('Netwerk', $groepen[0]['naam']);
        $this->assertCount(2, $groepen[0]['bundels'][0]['items']);
        $this->assertSame('Cloud', $groepen[1]['naam']);
        $this->assertCount(1, $groepen[1]['bundels'][0]['items']);
    }

    /**
     * De naamloze groep komt vooraan.
     *
     * Daar horen de losse cijfers: ze zijn niet minder belangrijk, ze
     * horen alleen nergens bij -- en dan staan ze boven het eerste
     * kopje in plaats van eronder.
     */
    public function test_the_group_without_a_name_comes_first(): void
    {
        Statistic::factory()->inGroep('Netwerk')->create(['position' => 1]);
        Statistic::factory()->create(['group_nl' => null, 'position' => 2]);

        $groepen = $this->opDeSite();

        $this->assertNull($groepen[0]['naam']);
        $this->assertSame('Netwerk', $groepen[1]['naam']);
    }

    /**
     * De volgorde van de groepen volgt hun eerste item.
     *
     * Daarmee bepaalt de klant met slepen ook de volgorde van de
     * groepen, zonder dat daar een apart scherm voor nodig is.
     */
    public function test_the_order_of_the_groups_follows_the_first_item(): void
    {
        Statistic::factory()->inGroep('Cloud')->create(['position' => 5]);
        Statistic::factory()->inGroep('Netwerk')->create(['position' => 1]);
        Statistic::factory()->inGroep('Cloud')->create(['position' => 9]);

        $namen = array_map(
            fn (array $groep) => $groep['naam'],
            $this->opDeSite(),
        );

        $this->assertSame(['Netwerk', 'Cloud'], $namen);
    }

    public function test_the_order_within_a_group_follows_position(): void
    {
        Statistic::factory()->inGroep('Cloud')->create([
            'label_nl' => 'Derde',
            'position' => 3,
        ]);
        Statistic::factory()->inGroep('Cloud')->create([
            'label_nl' => 'Eerste',
            'position' => 1,
        ]);
        Statistic::factory()->inGroep('Cloud')->create([
            'label_nl' => 'Tweede',
            'position' => 2,
        ]);

        $namen = array_map(
            fn (array $rij) => $rij['naam'],
            $this->opDeSite()[0]['bundels'][0]['items'],
        );

        $this->assertSame(['Eerste', 'Tweede', 'Derde'], $namen);
    }

    /** Het Engelse kopje wordt gebruikt als het er is. */
    public function test_an_english_visitor_gets_the_english_heading(): void
    {
        Statistic::factory()->create([
            'group_nl' => 'Beveiliging',
            'group_en' => 'Security',
        ]);

        $this->assertSame('Beveiliging', $this->opDeSite()[0]['naam']);
        $this->assertSame('Security', $this->opDeSite('en')[0]['naam']);
    }

    /**
     * Zonder Engels kopje valt de groep terug op het Nederlands.
     *
     * Anders dan bij de meeste optionele velden, waar leeg betekent
     * "laat maar weg": een groep zonder kop is een streep zonder
     * uitleg.
     */
    public function test_a_missing_english_heading_falls_back_to_dutch(): void
    {
        Statistic::factory()->create([
            'group_nl' => 'Beveiliging',
            'group_en' => null,
        ]);

        $this->assertSame('Beveiliging', $this->opDeSite('en')[0]['naam']);
    }

    /**
     * **De indeling is in beide talen dezelfde.**
     *
     * Twee items in dezelfde Nederlandse groep, waarvan er één wél een
     * Engels kopje heeft en de ander niet. Zou er op de vertaalde
     * waarde gegroepeerd worden, dan vallen ze in het Engels uit elkaar
     * in twee groepen.
     */
    public function test_a_half_translated_group_does_not_split_in_english(): void
    {
        Statistic::factory()->create([
            'group_nl' => 'Beveiliging',
            'group_en' => 'Security',
            'position' => 1,
        ]);
        Statistic::factory()->create([
            'group_nl' => 'Beveiliging',
            'group_en' => null,
            'position' => 2,
        ]);

        $groepen = $this->opDeSite('en');

        $this->assertCount(1, $groepen);
        $this->assertCount(2, $groepen[0]['bundels'][0]['items']);
    }

    /**
     * Een groep die eruitziet als een getal duwt de pagina niet om.
     *
     * PHP maakt van een array-sleutel die eruitziet als een getal
     * stilletjes een integer, en de groepen worden op hun sleutel
     * gesorteerd. Noemt de klant een groep "2024", dan komt daar dus
     * geen string binnen maar een `int`.
     *
     * Dat valt zonder `declare(strict_types)` niet op, en precies
     * daarom staat deze test er: hij houdt het vast vóórdat iemand die
     * declaratie ooit aanzet en de voorpagina eruit ligt op een
     * groepsnaam.
     */
    public function test_a_group_that_looks_like_a_number_still_works(): void
    {
        Statistic::factory()->create(['group_nl' => '2024', 'position' => 1]);
        Statistic::factory()->create(['group_nl' => 'Netwerk', 'position' => 2]);
        Statistic::factory()->create(['group_nl' => null, 'position' => 3]);

        $groepen = $this->opDeSite();

        $this->assertCount(3, $groepen);

        // De naamloze blijft vooraan, ook met een getal in de rij.
        $this->assertNull($groepen[0]['naam']);
        $this->assertSame('2024', $groepen[1]['naam']);
        $this->assertSame('Netwerk', $groepen[2]['naam']);

        // En de sleutel komt als tekst terug, niet als getal.
        $this->assertSame('2024', $groepen[1]['sleutel']);
    }

    /** De naam en de notitie volgen de gewone terugvalregels. */
    public function test_the_label_falls_back_but_the_note_does_not(): void
    {
        Statistic::factory()->create([
            'label_nl' => 'Netwerkbeheer',
            'label_en' => null,
            'note_nl' => 'Van switch tot firewall.',
            'note_en' => null,
        ]);

        $rij = $this->opDeSite('en')[0]['bundels'][0]['items'][0];

        $this->assertSame('Netwerkbeheer', $rij['naam']);
        $this->assertNull($rij['notitie']);
    }

    /**
     * De naam van een groep kan op zichzelf vertaald worden.
     *
     * Dat is wat het kleine knopje in het indelingsvenster doet: één veld
     * erin, één veld eruit. Hetzelfde adres als de grote vertaalknop in
     * het bewerkvenster, want een tweede route ernaast zou dezelfde
     * begrenzing en foutafhandeling moeten herhalen.
     *
     * Welk vak het vroeg weet de server niet en hoeft hij niet te weten;
     * het venster onthoudt dat zelf.
     */
    public function test_a_group_name_can_be_translated_on_its_own(): void
    {
        $this->app->instance(Vertaler::class, new class implements Vertaler
        {
            public function beschikbaar(): bool
            {
                return true;
            }

            public function naarEngels(array $teksten): array
            {
                return array_map(
                    fn (string $tekst) => 'EN: '.$tekst,
                    array_filter($teksten, fn (?string $tekst) => filled($tekst)),
                );
            }
        });

        $beheerder = User::factory()->create();
        $beheerder->givePermissionTo('manage portal');

        $this->actingAs($beheerder)
            ->from(route('website.statistieken.index'))
            ->post(route('website.vertalen'), ['groep_nl' => 'Netwerk'])
            ->assertRedirect(route('website.statistieken.index'));

        $this->assertSame(
            ['groep_en' => 'EN: Netwerk'],
            session(SessionKey::FLASH_DATA)['vertaling'] ?? null,
        );
    }
}
