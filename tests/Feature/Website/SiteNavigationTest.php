<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\Experience;
use App\Models\PageSection;
use Database\Seeders\ExperienceSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het menu in de kop van de publieke site.
 *
 * **Dit is de test die er niet was toen het misging.** Het menu stond
 * hardgecodeerd in SiteHeader.vue en liep dus niet mee met de indeling die
 * de klant zelf bepaalt: Ervaring ontbrak, een uitgezet onderdeel hield
 * zijn link naar een anker dat niet bestond, en na een herordening stond
 * het menu in de oude volgorde.
 *
 * Dat is precies het soort fout dat niemand ziet. Het scherm blijft er
 * goed uitzien; alleen doet een link niets meer. Vandaar dat hier niet
 * wordt gecontroleerd of er een menu is, maar of het **hetzelfde** is als
 * wat er op de pagina staat -- dezelfde onderdelen, in dezelfde volgorde.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class SiteNavigationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(ExperienceSeeder::class);
    }

    /**
     * @return array<int, string>
     */
    private function menu(): array
    {
        $sleutels = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$sleutels) {
            $sleutels = array_column($page->toArray()['props']['navigation'], 'key');
        });

        return $sleutels;
    }

    /**
     * @return array<int, string>
     */
    private function pagina(): array
    {
        $sleutels = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$sleutels) {
            $sleutels = $page->toArray()['props']['sections'];
        });

        return $sleutels;
    }

    public function test_the_menu_matches_the_sections_on_the_page(): void
    {
        $this->assertSame($this->pagina(), $this->menu());
        $this->assertNotEmpty($this->menu());
    }

    public function test_every_item_carries_a_label(): void
    {
        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page->has(
                'navigation.0',
                fn (AssertableInertia $item) => $item
                    ->has('key')
                    ->whereNot('label', '')
                    ->etc(),
            ),
        );
    }

    public function test_a_section_the_owner_switched_off_leaves_the_menu(): void
    {
        PageSection::query()
            ->where('key', PageSectionKey::Werkwijze)
            ->update(['visible' => false]);

        $menu = $this->menu();

        $this->assertNotContains(PageSectionKey::Werkwijze->value, $menu);
        $this->assertSame($this->pagina(), $menu);
    }

    public function test_the_menu_follows_a_new_order(): void
    {
        /*
         * Contact naar voren halen. De klant sleept dat op het
         * indelingsscherm; hier zetten we het rechtstreeks in de database,
         * want het gaat om wat de landing er daarna van maakt.
         */
        PageSection::query()
            ->where('key', PageSectionKey::Contact)
            ->update(['position' => -1]);

        $menu = $this->menu();

        $this->assertSame(PageSectionKey::Contact->value, $menu[0]);
        $this->assertSame($this->pagina(), $menu);
    }

    public function test_an_empty_section_is_in_neither(): void
    {
        /*
         * Ervaring zonder ervaringen. Die staat aan, maar er is niets te
         * tonen -- dus hij hoort niet op de pagina en niet in het menu.
         * Zou hij alleen uit het menu verdwijnen en niet van de pagina
         * (of andersom), dan loopt het weer uiteen.
         */
        Experience::query()->delete();

        $menu = $this->menu();

        $this->assertNotContains(PageSectionKey::Ervaring->value, $menu);
        $this->assertSame($this->pagina(), $menu);
    }
}
