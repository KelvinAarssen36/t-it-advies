<?php

namespace Tests\Feature\Website;

use App\Enums\ContactWeergave;
use App\Enums\PageSectionKey;
use App\Models\AboutPoint;
use App\Models\AboutSetting;
use App\Models\ContactSetting;
use App\Models\PageSection;
use Database\Seeders\ContactSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het menu van de publieke site, op de voorpagina én daarbuiten.
 *
 * **Dit is de test bij een correctie die drie pagina's raakte.** De kop
 * verving zijn hele navigatiebalk door één "Terug naar de website" zodra
 * het menu leeg was, en dat was het op elke subpagina -- `navigation` kwam
 * alleen uit HomeController. Omdat die balk dan niets nuttigs deed, zette
 * elke subpagina er ook nog een eigen teruglink bij. Op de
 * privacyverklaring stonden er zo drie, met twee verschillende woorden
 * voor dezelfde bestemming.
 *
 * Nu sturen alle publieke pagina's hetzelfde menu mee en is de navigatie
 * zelf de weg terug. Wat hier wordt bewaakt is dat dat menu op alle vier
 * de pagina's hetzelfde is: zou een subpagina zijn eigen lijst krijgen,
 * dan loopt het menu daar uit de pas met wat de eigenaar heeft ingesteld
 * -- en dat is precies de fout die `navigation` ooit moest oplossen.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class SiteNavigatieTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
        $this->seed(ContactSeeder::class);
    }

    /** De aparte pagina "Over mij" bestaat pas met een verhaal erin. */
    private function overMijKlaar(): void
    {
        AboutSetting::query()->firstOrNew()->fill([
            'summary_nl' => 'Een korte samenvatting.',
            'page_enabled' => true,
            'story_nl' => 'Een verhaal van een paar regels.',
        ])->save();

        AboutPoint::factory()->create();
    }

    /** En de aparte contactpagina pas als de eigenaar daarvoor kiest. */
    private function contactpaginaAan(): void
    {
        ContactSetting::huidige()->fill([
            'display' => ContactWeergave::EigenPagina,
        ])->save();
    }

    /**
     * Het menu van een pagina, als lijst sleutels.
     *
     * @return array<int, string>
     */
    private function menuVan(string $adres): array
    {
        $antwoord = $this->get($adres)->assertOk();

        $props = $antwoord->viewData('page')['props'] ?? [];

        /** @var array<int, array{key: string, label: string}> $menu */
        $menu = $props['navigation'] ?? [];

        return array_map(fn (array $item) => $item['key'], $menu);
    }

    /* --- Het menu staat overal ------------------------------------------- */

    public function test_the_front_page_has_a_menu(): void
    {
        $this->assertNotEmpty($this->menuVan(route('home')));
    }

    /**
     * En de subpagina's hebben hetzelfde menu.
     *
     * Niet "een menu" maar hetzelfde: de volgorde en de inhoud komen van de
     * eigenaar, en een tweede lijst zou daarvan af kunnen wijken.
     */
    public function test_the_sub_pages_have_the_same_menu(): void
    {
        $this->overMijKlaar();
        $this->contactpaginaAan();

        $voorpagina = $this->menuVan(route('home'));

        $this->assertSame($voorpagina, $this->menuVan(route('privacy')));
        $this->assertSame($voorpagina, $this->menuVan(route('over-mij')));
        $this->assertSame($voorpagina, $this->menuVan(route('contact')));
    }

    /**
     * Een uitgezet onderdeel staat ook op een subpagina niet in het menu.
     *
     * Met "Over mij" en niet met een willekeurig onderdeel: het menu toont
     * alleen wat gevuld is, en dit is het onderdeel dat deze test zelf vult.
     * Een leeg onderdeel staat er sowieso niet in, en dan bewijst het
     * uitzetten niets.
     */
    public function test_a_switched_off_section_is_gone_everywhere(): void
    {
        $this->overMijKlaar();

        $this->assertContains('over-mij', $this->menuVan(route('privacy')));

        PageSection::query()
            ->where('key', PageSectionKey::OverMij->value)
            ->update(['visible' => false]);

        $this->assertNotContains('over-mij', $this->menuVan(route('home')));
        $this->assertNotContains('over-mij', $this->menuVan(route('privacy')));
    }

    /** En een andere volgorde geldt ook daar. */
    public function test_the_order_follows_the_owner_everywhere(): void
    {
        $voor = $this->menuVan(route('home'));

        $this->assertGreaterThan(1, count($voor));

        // De eerste twee omdraaien, zoals slepen op de indelingspagina doet.
        $eerste = PageSection::query()->where('key', $voor[0])->sole();
        $tweede = PageSection::query()->where('key', $voor[1])->sole();

        [$eerste->position, $tweede->position] = [$tweede->position, $eerste->position];

        $eerste->save();
        $tweede->save();

        $na = $this->menuVan(route('privacy'));

        $this->assertSame($voor[1], $na[0]);
        $this->assertSame($voor[0], $na[1]);
    }

    /* --- En de teruglinks zijn weg --------------------------------------- */

    /**
     * De privacyverklaring heeft geen eigen teruglinks meer.
     *
     * Er stonden er twee -- boven en onder -- naast die in de kop. De
     * verdediging daarvoor was dat je zonder die link vastzit, en dat was
     * niet waar: `.brand-sitekop` is `position: sticky`, dus de navigatie
     * blijft in beeld. Wat ervoor in de plaats komt is het kruimelpad
     * boven en één knop onder.
     */
    public function test_the_privacy_page_no_longer_repeats_the_way_back(): void
    {
        $html = $this->get(route('privacy'))->assertOk()->getContent();

        $this->assertSame(
            0,
            substr_count((string) $html, 'Terug naar de website'),
            'De oude teruglink staat nog op de privacyverklaring.',
        );
    }

    /** En het menu komt mee in de props, ook daar. */
    public function test_the_privacy_page_knows_the_menu(): void
    {
        $this->get(route('privacy'))->assertInertia(
            fn (AssertableInertia $page) => $page
                ->component('public/Privacy')
                ->has('navigation')
                ->etc()
        );
    }
}
