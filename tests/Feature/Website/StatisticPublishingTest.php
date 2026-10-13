<?php

namespace Tests\Feature\Website;

use App\Models\Statistic;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Online, offline en de volgorde van de statistieken.
 *
 * Wat de eigenaar uitzet hoort **nergens** meer te staan -- niet op de
 * website, en niet in de telling die bepaalt of het onderdeel nog
 * inhoud heeft. Die twee uit elkaar laten lopen levert een
 * indelingsscherm op dat zegt dat er iets staat terwijl de bezoeker
 * niets ziet.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
class StatisticPublishingTest extends TestCase
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

    /** De namen zoals de website ze meestuurt, over alle groepen heen. */
    private function opDeSite(): array
    {
        $namen = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$namen) {
            foreach ($page->toArray()['props']['statistics'] as $groep) {
                foreach ($groep['bundels'] as $bundel) {
                    foreach ($bundel['items'] as $rij) {
                        $namen[] = $rij['naam'];
                    }
                }
            }
        });

        return $namen;
    }

    private function sectiesOpDeSite(): array
    {
        $secties = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$secties) {
            $secties = $page->toArray()['props']['sections'];
        });

        return $secties;
    }

    public function test_the_switch_puts_a_statistic_online_and_offline(): void
    {
        $statistiek = Statistic::factory()->offline()->create();

        $this->actingAs($this->beheerder())->patch(
            route('website.statistieken.online', $statistiek),
            ['published' => true],
        );

        $this->assertTrue($statistiek->fresh()?->published);

        $this->actingAs($this->beheerder())->patch(
            route('website.statistieken.online', $statistiek),
            ['published' => false],
        );

        $this->assertFalse($statistiek->fresh()?->published);
    }

    public function test_what_is_offline_is_not_on_the_website(): void
    {
        Statistic::factory()->create(['label_nl' => 'Zichtbaar', 'position' => 1]);
        Statistic::factory()->offline()->create(['label_nl' => 'Verborgen', 'position' => 2]);

        $this->assertSame(['Zichtbaar'], $this->opDeSite());
    }

    public function test_the_whole_section_disappears_when_everything_is_offline(): void
    {
        Statistic::factory()->offline()->create();

        // Een kopje met niets eronder is slordiger dan geen kopje.
        $this->assertNotContains('statistieken', $this->sectiesOpDeSite());
    }

    public function test_the_section_comes_back_as_soon_as_there_is_one_online(): void
    {
        Statistic::factory()->create();

        $this->assertContains('statistieken', $this->sectiesOpDeSite());
    }

    /**
     * Het verzoek zoals het indelingsvenster het stuurt.
     *
     * Per regel een id én een groep, want dat venster past allebei aan.
     *
     * @param  array<int, Statistic>  $rij
     * @return array<int, array{id: int, groep: string|null}>
     */
    private function alsIndeling(array $rij): array
    {
        return array_map(
            fn (Statistic $item) => ['id' => $item->id, 'groep' => $item->group_nl],
            $rij,
        );
    }

    public function test_the_order_can_be_changed(): void
    {
        $een = Statistic::factory()->create(['label_nl' => 'Een', 'position' => 1]);
        $twee = Statistic::factory()->create(['label_nl' => 'Twee', 'position' => 2]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => $this->alsIndeling([$twee, $een])],
        )->assertSessionHasNoErrors();

        $this->assertSame(['Twee', 'Een'], $this->opDeSite());
    }

    /**
     * Datzelfde venster verhuist een statistiek naar een andere groep.
     *
     * **Dit is de reden dat de route meer doet dan de volgorde.** De
     * klant kon een statistiek alleen van groep wisselen in het
     * bewerkvenster, terwijl hij in de sleeplijst stond te schuiven en
     * zich afvroeg waarom dat niets deed. Nu kan het daar.
     */
    public function test_a_statistic_can_be_moved_to_another_group(): void
    {
        $item = Statistic::factory()->inGroep('Cloud')->create(['position' => 1]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => [['id' => $item->id, 'groep' => 'Netwerk']]],
        )->assertSessionHasNoErrors();

        $this->assertSame('Netwerk', $item->fresh()?->group_nl);
    }

    /** En eruit halen kan ook: een lege groep betekent "los bovenaan". */
    public function test_a_statistic_can_be_taken_out_of_its_group(): void
    {
        $item = Statistic::factory()->inGroep('Cloud')->create(['position' => 1]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => [['id' => $item->id, 'groep' => null]]],
        )->assertSessionHasNoErrors();

        $this->assertNull($item->fresh()?->group_nl);
    }

    /**
     * De Engelse groepsnaam verhuist mee.
     *
     * Het kopje van een groep komt van zijn eerste item. Zou een
     * verhuizer zijn oude Engelse naam houden, dan ziet een Engelse
     * bezoeker een kopje dat een Nederlandse bezoeker niet ziet; zou hij
     * er geen krijgen, dan verliest de hele groep zijn Engelse naam
     * zodra hij vooraan komt.
     */
    public function test_moving_to_another_group_adopts_its_english_name(): void
    {
        $blijft = Statistic::factory()->create([
            'group_nl' => 'Netwerk',
            'group_en' => 'Network',
            'position' => 1,
        ]);

        $verhuist = Statistic::factory()->create([
            'group_nl' => 'Cloud',
            'group_en' => 'De wolk',
            'position' => 2,
        ]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => [
                ['id' => $blijft->id, 'groep' => 'Netwerk'],
                ['id' => $verhuist->id, 'groep' => 'Netwerk'],
            ]],
        )->assertSessionHasNoErrors();

        $this->assertSame('Netwerk', $verhuist->fresh()?->group_nl);

        // De Engelse naam van zijn nieuwe groep, niet die van de oude.
        $this->assertSame('Network', $verhuist->fresh()?->group_en);
    }

    /**
     * Naar een groep die nog geen Engelse naam heeft: dan ook niet.
     *
     * Hij mag die van zijn oude groep niet meeslepen -- dan krijgt de
     * nieuwe groep een Engels kopje dat niemand heeft ingevuld.
     */
    public function test_moving_to_a_group_without_an_english_name_clears_it(): void
    {
        Statistic::factory()->create([
            'group_nl' => 'Netwerk',
            'group_en' => null,
            'position' => 1,
        ]);

        $verhuist = Statistic::factory()->create([
            'group_nl' => 'Cloud',
            'group_en' => 'De wolk',
            'position' => 2,
        ]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => [
                ['id' => Statistic::query()->where('group_nl', 'Netwerk')->value('id'), 'groep' => 'Netwerk'],
                ['id' => $verhuist->id, 'groep' => 'Netwerk'],
            ]],
        )->assertSessionHasNoErrors();

        $this->assertNull($verhuist->fresh()?->group_en);
    }

    /**
     * Het indelingsvenster stuurt de Engelse groepsnaam mee.
     *
     * **Daar is de groep een vak met twee naamvelden**, en alles wat in
     * dat vak ligt hoort dezelfde twee namen te krijgen. Zie
     * StatistiekIndelingDialoog.vue.
     *
     * Dit is het geval waar het mis zou gaan zonder: hernoemt de klant
     * een groep, dan bestaat de nieuwe naam nog nergens, en dan zou de
     * terugval (`engelseGroep()`) niets vinden en het Engelse kopje
     * weggooien.
     */
    public function test_renaming_a_group_keeps_its_english_name(): void
    {
        $een = Statistic::factory()->create([
            'group_nl' => 'Netwerk',
            'group_en' => 'Network',
            'position' => 1,
        ]);

        $twee = Statistic::factory()->create([
            'group_nl' => 'Netwerk',
            'group_en' => 'Network',
            'position' => 2,
        ]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => [
                ['id' => $een->id, 'groep' => 'Netwerken', 'groep_en' => 'Networking'],
                ['id' => $twee->id, 'groep' => 'Netwerken', 'groep_en' => 'Networking'],
            ]],
        )->assertSessionHasNoErrors();

        foreach ([$een, $twee] as $statistiek) {
            $this->assertSame('Netwerken', $statistiek->fresh()?->group_nl);
            $this->assertSame('Networking', $statistiek->fresh()?->group_en);
        }
    }

    /**
     * En het repareert een groep die twee Engelse koppen had.
     *
     * Dat kon ontstaan doordat elk cijfer zijn eigen groepsnaam bewaarde:
     * vul je in het bewerkvenster bij het ene "Network" in en bij het
     * andere niets, dan hangt het Engelse kopje van de groep af van wie
     * er vooraan staat. Het vak in het indelingsvenster heeft één naam,
     * dus zodra hij daar opslaat is dat verschil weg.
     */
    public function test_the_layout_window_gives_a_group_one_english_name(): void
    {
        $een = Statistic::factory()->create([
            'group_nl' => 'Cloud',
            'group_en' => 'The cloud',
            'position' => 1,
        ]);

        $twee = Statistic::factory()->create([
            'group_nl' => 'Cloud',
            'group_en' => null,
            'position' => 2,
        ]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => [
                ['id' => $een->id, 'groep' => 'Cloud', 'groep_en' => 'The cloud'],
                ['id' => $twee->id, 'groep' => 'Cloud', 'groep_en' => 'The cloud'],
            ]],
        )->assertSessionHasNoErrors();

        $this->assertSame('The cloud', $een->fresh()?->group_en);
        $this->assertSame('The cloud', $twee->fresh()?->group_en);
    }

    /**
     * Zonder groep hoort er ook geen Engelse groepsnaam te staan.
     *
     * Anders sleept een los cijfer een kopje met zich mee dat nergens te
     * zien is -- tot het weer in een groep belandt en ineens het
     * verkeerde Engelse kopje oplevert.
     */
    public function test_a_statistic_without_a_group_has_no_english_group_name(): void
    {
        $item = Statistic::factory()->create([
            'group_nl' => 'Netwerk',
            'group_en' => 'Network',
            'position' => 1,
        ]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => [
                ['id' => $item->id, 'groep' => null, 'groep_en' => 'Network'],
            ]],
        )->assertSessionHasNoErrors();

        $this->assertNull($item->fresh()?->group_nl);
        $this->assertNull($item->fresh()?->group_en);
    }

    /** Een te lange Engelse groepsnaam wordt geweigerd. */
    public function test_an_english_group_name_that_is_too_long_is_refused(): void
    {
        $een = Statistic::factory()->create(['position' => 1]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => [
                [
                    'id' => $een->id,
                    'groep' => 'Netwerk',
                    'groep_en' => str_repeat('a', 61),
                ],
            ]],
        )->assertSessionHasErrors('statistieken.0.groep_en');
    }

    /**
     * Een halve lijst wordt geweigerd.
     *
     * Zou een verzoek met twee van de drie ids doorgaan, dan krijgen
     * die twee positie 1 en 2 en botsen ze met de derde.
     */
    public function test_an_incomplete_order_is_refused(): void
    {
        $een = Statistic::factory()->create(['position' => 1]);
        Statistic::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => $this->alsIndeling([$een])],
        );

        $this->assertSame(1, (int) $een->fresh()?->position);
    }

    public function test_an_unknown_id_in_the_order_is_refused(): void
    {
        $een = Statistic::factory()->create(['position' => 1]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => [
                ['id' => $een->id, 'groep' => null],
                ['id' => 9999, 'groep' => null],
            ]],
        )->assertSessionHasErrors('statistieken.1.id');
    }

    /** Een groepsnaam die te lang is wordt geweigerd. */
    public function test_a_group_name_that_is_too_long_is_refused(): void
    {
        $een = Statistic::factory()->create(['position' => 1]);

        $this->actingAs($this->beheerder())->put(
            route('website.statistieken.volgorde'),
            ['statistieken' => [
                ['id' => $een->id, 'groep' => str_repeat('a', 61)],
            ]],
        )->assertSessionHasErrors('statistieken.0.groep');
    }

    public function test_the_layout_screen_counts_only_what_is_online(): void
    {
        Statistic::factory()->count(2)->create();
        Statistic::factory()->offline()->create();

        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $rijen = collect($page->toArray()['props']['sections']);
                $rij = $rijen->firstWhere('key', 'statistieken');

                $this->assertSame(2, $rij['count']);
                $this->assertTrue($rij['filled']);
            });
    }

    public function test_the_layout_screen_flags_an_empty_section(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $rijen = collect($page->toArray()['props']['sections']);
                $rij = $rijen->firstWhere('key', 'statistieken');

                $this->assertSame(0, $rij['count']);
                $this->assertFalse($rij['filled']);
                $this->assertFalse($rij['live']);

                // En er is een scherm om het op te lossen.
                $this->assertNotNull($rij['manageUrl']);
            });
    }
}
