<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\PageSection;
use App\Models\User;
use App\Support\Page\SectionContent;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het LinkedIn-blok op de landingspagina: het omhulsel.
 *
 * Het adres staat in `config/site.php` en ligt bewust vast, want een
 * tikfout in een link naar buiten is een dode knop op de voorpagina. Wat
 * hier wordt nagelopen: dat het adres bij de bezoeker aankomt, dat het een
 * échte link naar LinkedIn is, en dat de klant dit onderdeel net als elk
 * ander kan verslepen en uitzetten. Het beheer van de teksten staat in
 * LinkedinCrudTest, het terugzetten in BackupTerugzetTest.
 *
 * Zie docs/architecture/modules/linkedin.md.
 */
class LinkedinSectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function landing(): array
    {
        return $this->get(route('home'))->viewData('page')['props'];
    }

    public function test_the_address_reaches_the_visitor(): void
    {
        $props = $this->landing();

        $this->assertSame(config('site.linkedin'), $props['linkedin']);
        $this->assertSame(
            config('security.portal_account.name'),
            $props['eigenaar'],
        );
    }

    /**
     * Het adres is een echte LinkedIn-link.
     *
     * Dit is geen formaliteit. Het adres heeft geen beheerscherm en dus geen
     * validatie die meekijkt; de enige bescherming tegen een adres dat
     * nergens heen gaat -- of naar iets heel anders -- is deze test.
     */
    public function test_the_address_is_a_real_linkedin_url(): void
    {
        $adres = (string) config('site.linkedin');

        $this->assertNotSame('', $adres);
        $this->assertTrue(
            str_starts_with($adres, 'https://www.linkedin.com/'),
            'Het LinkedIn-adres hoort een https-link naar linkedin.com te zijn.',
        );
        $this->assertNotFalse(filter_var($adres, FILTER_VALIDATE_URL));
    }

    /**
     * Het adres kan uit de omgeving komen, maar nooit uit het niets.
     *
     * Zou er per ongeluk een lege waarde in `.env` staan, dan verdwijnt
     * de knop van de website zonder dat iemand het merkt. De
     * standaardwaarde in de config vangt dat op.
     */
    public function test_the_config_falls_back_to_the_real_address(): void
    {
        $this->assertStringContainsString(
            'linkedin.com/in/',
            (string) config('site.linkedin'),
        );
    }

    public function test_the_section_is_on_the_landing_page(): void
    {
        $this->assertContains(
            PageSectionKey::Linkedin->value,
            $this->landing()['sections'],
        );
    }

    public function test_the_owner_can_switch_it_off(): void
    {
        PageSection::query()
            ->where('key', PageSectionKey::Linkedin)
            ->update(['visible' => false]);

        $this->assertNotContains(
            PageSectionKey::Linkedin->value,
            $this->landing()['sections'],
        );
    }

    public function test_it_can_be_moved(): void
    {
        // Niet vast, anders dan de kop en de voettekst: waar de
        // uitnodiging staat is een keuze van de klant.
        $this->assertFalse(PageSectionKey::Linkedin->vast());

        PageSection::query()
            ->where('key', PageSectionKey::Linkedin)
            ->update(['position' => 0]);

        $this->assertSame(
            PageSectionKey::Linkedin->value,
            $this->landing()['sections'][0],
        );
    }

    /**
     * Het indelingsscherm wijst naar het beheerscherm van dit blok.
     *
     * Hier stond eerst de omgekeerde test: "er valt niets te beheren".
     * Dat was waar zolang er alleen een vaste link was. Sinds de teksten
     * van het blok te beheren zijn, hoort het indelingsscherm ernaar te
     * linken, net als bij elk ander onderdeel.
     */
    public function test_the_layout_screen_links_to_the_management_screen(): void
    {
        $beheerder = User::factory()->create();
        $beheerder->givePermissionTo('manage portal');

        /*
         * De regel wordt opgezocht en niet op plek geteld. Een vaste
         * index zou omvallen zodra er een onderdeel bij komt, en dan
         * gaat deze test over iets anders dan waar hij over gaat.
         */
        $rijen = [];

        $this->actingAs($beheerder)
            ->get(route('website.index'))
            ->assertInertia(function (AssertableInertia $page) use (&$rijen) {
                $rijen = $page->toArray()['props']['sections'];
            });

        $regel = collect($rijen)
            ->firstWhere('key', PageSectionKey::Linkedin->value);

        $this->assertNotNull($regel);
        $this->assertSame(route('website.linkedin.index'), $regel['manageUrl']);
        $this->assertTrue($regel['manageable']);
    }

    public function test_it_never_counts_as_empty(): void
    {
        /*
         * Er is geen teller voor dit onderdeel, en dat is de bedoeling:
         * de kop valt altijd terug op de standaardtekst en kan dus niet
         * leeg zijn. Zonder
         * die afspraak zou het blok van de website vallen omdat niemand
         * eraan dacht een teller aan te melden.
         */
        $this->assertTrue(
            app(SectionContent::class)
                ->gevuld(PageSectionKey::Linkedin),
        );
    }
}
