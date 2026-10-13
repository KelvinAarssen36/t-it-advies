<?php

namespace Tests\Feature\Website;

use App\Enums\StatisticDisplay;
use App\Models\Statistic;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De drie weergaven, en de grens die eraan hangt.
 *
 * **Dit is de enige validatieregel in dit project die van een ándere
 * waarde afhangt.** Een balk of ring tekent een deel van een geheel en
 * komt dus nooit boven de honderd; een teller heeft geen geheel. Zo'n
 * regel is precies het soort ding dat stilletjes verdwijnt bij een
 * herschrijving -- en dan staat er een balk van 340 procent op de
 * voorpagina.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
class StatisticDisplayTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $anders
     */
    private function maak(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())->post(
            route('website.statistieken.store'),
            array_merge([
                'display' => 'balk',
                'published' => true,
                'label_nl' => 'Iets',
                'value' => 50,
            ], $anders),
        );
    }

    /** De groepen zoals de website ze meestuurt. */
    private function opDeSite(): array
    {
        $groepen = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$groepen) {
            $groepen = $page->toArray()['props']['statistics'];
        });

        return $groepen;
    }

    public function test_every_display_reaches_the_website_in_its_own_bundle(): void
    {
        Statistic::factory()->create(['label_nl' => 'Een balk', 'position' => 1]);
        Statistic::factory()->ring()->create(['label_nl' => 'Een ring', 'position' => 2]);
        Statistic::factory()->teller()->create(['label_nl' => 'Een teller', 'position' => 3]);

        $groepen = $this->opDeSite();

        $this->assertCount(1, $groepen);

        /*
         * Drie banden, in de volgorde die de eigenaar heeft gezet --
         * dus balk, ring, teller en niet de vaste volgorde ring, teller,
         * balk die hier eerst stond.
         */
        $bundels = $groepen[0]['bundels'];

        $this->assertCount(3, $bundels);
        $this->assertSame('balk', $bundels[0]['weergave']);
        $this->assertSame('Een balk', $bundels[0]['items'][0]['naam']);
        $this->assertSame('ring', $bundels[1]['weergave']);
        $this->assertSame('Een ring', $bundels[1]['items'][0]['naam']);
        $this->assertSame('teller', $bundels[2]['weergave']);
        $this->assertSame('Een teller', $bundels[2]['items'][0]['naam']);
    }

    /**
     * Opeenvolgende statistieken van dezelfde vorm komen op één rij.
     *
     * Dit is de andere helft van de bundeling: de volgorde van de
     * eigenaar wordt gevolgd, en er begint alleen een nieuwe band zodra
     * de vorm verandert. Twee ringen naast elkaar in zijn lijst staan op
     * de site dus ook naast elkaar.
     */
    public function test_consecutive_statistics_of_the_same_shape_share_a_band(): void
    {
        Statistic::factory()->ring()->create(['label_nl' => 'Ring een', 'position' => 1]);
        Statistic::factory()->ring()->create(['label_nl' => 'Ring twee', 'position' => 2]);
        Statistic::factory()->create(['label_nl' => 'Balk', 'position' => 3]);
        Statistic::factory()->ring()->create(['label_nl' => 'Ring drie', 'position' => 4]);

        $bundels = $this->opDeSite()[0]['bundels'];

        // Twee ringen samen, dan de balk, dan de derde ring apart.
        $this->assertCount(3, $bundels);
        $this->assertCount(2, $bundels[0]['items']);
        $this->assertSame('Ring een', $bundels[0]['items'][0]['naam']);
        $this->assertSame('Ring twee', $bundels[0]['items'][1]['naam']);
        $this->assertSame('balk', $bundels[1]['weergave']);
        $this->assertCount(1, $bundels[2]['items']);
        $this->assertSame('Ring drie', $bundels[2]['items'][0]['naam']);
    }

    /**
     * **Slepen doet altijd iets zichtbaars.**
     *
     * Dit is de fout die de klant meldde: hij sleepte iets in de tabel
     * en zag op zijn website niets veranderen. De oorzaak was dat alle
     * ringen altijd vooraan kwamen, hoe hij ook sleepte -- de
     * sleepgreep was dus een knop die loog.
     *
     * Deze test draait twee statistieken van verschillende vorm om en
     * eist dat de uitkomst verandert.
     */
    public function test_dragging_a_bar_above_a_ring_is_visible_on_the_website(): void
    {
        $ring = Statistic::factory()->ring()->create([
            'label_nl' => 'De ring',
            'position' => 1,
        ]);
        $balk = Statistic::factory()->create([
            'label_nl' => 'De balk',
            'position' => 2,
        ]);

        $this->assertSame('ring', $this->opDeSite()[0]['bundels'][0]['weergave']);

        // Zoals slepen het opslaat: de twee wisselen van plek.
        $ring->update(['position' => 2]);
        $balk->update(['position' => 1]);

        $this->assertSame('balk', $this->opDeSite()[0]['bundels'][0]['weergave']);
    }

    public function test_a_bar_above_a_hundred_is_refused(): void
    {
        $this->maak(['display' => 'balk', 'value' => 150])
            ->assertSessionHasErrors('value');

        $this->assertSame(0, Statistic::query()->count());
    }

    public function test_a_ring_above_a_hundred_is_refused(): void
    {
        $this->maak(['display' => 'ring', 'value' => 101])
            ->assertSessionHasErrors('value');

        $this->assertSame(0, Statistic::query()->count());
    }

    /** Een teller kent die grens niet; daar is het getal het getal. */
    public function test_a_counter_of_five_thousand_is_fine(): void
    {
        $this->maak(['display' => 'teller', 'value' => 5000])
            ->assertSessionHasNoErrors();

        $this->assertSame(5000, Statistic::query()->value('value'));
    }

    /**
     * Ook een teller heeft een grens, alleen een veel ruimere.
     *
     * Boven de zeven cijfers leest een getal op een voorpagina toch niet
     * meer, en het past niet naast twee andere.
     */
    public function test_a_counter_that_is_absurdly_large_is_refused(): void
    {
        $this->maak(['display' => 'teller', 'value' => 10_000_000])
            ->assertSessionHasErrors('value');

        $this->assertSame(0, Statistic::query()->count());
    }

    public function test_an_unknown_display_is_refused(): void
    {
        $this->maak(['display' => 'taartpunt'])
            ->assertSessionHasErrors('display');

        $this->assertSame(0, Statistic::query()->count());
    }

    /**
     * Een onbekende weergave valt terug op de strengste grens.
     *
     * Dat de weergave zelf al wordt geweigerd maakt dat niet overbodig:
     * zou de terugval de ruime grens zijn, dan hangt het van de
     * volgorde van de regels af of een balk van 340 er doorheen glipt.
     */
    public function test_a_broken_display_still_caps_the_value_at_a_hundred(): void
    {
        $this->maak(['display' => 'taartpunt', 'value' => 340])
            ->assertSessionHasErrors(['display', 'value']);
    }

    public function test_the_prefix_and_suffix_reach_the_website(): void
    {
        Statistic::factory()->teller()->create([
            'value' => 1200,
            'prefix' => '€',
            'suffix' => '+',
        ]);

        $rij = $this->opDeSite()[0]['bundels'][0]['items'][0];

        $this->assertSame('€', $rij['voor']);
        $this->assertSame('+', $rij['na']);
        $this->assertSame(1200, $rij['waarde']);
    }

    /** De grens staat op de enum, want het scherm toont hem ook. */
    public function test_the_enum_knows_its_own_limits(): void
    {
        $this->assertSame(100, StatisticDisplay::Balk->maximum());
        $this->assertSame(100, StatisticDisplay::Ring->maximum());
        $this->assertSame(9_999_999, StatisticDisplay::Teller->maximum());

        $this->assertTrue(StatisticDisplay::Balk->isPercentage());
        $this->assertFalse(StatisticDisplay::Teller->isPercentage());
    }
}
