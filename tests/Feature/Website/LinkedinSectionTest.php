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
 * Het LinkedIn-blok op de landingspagina.
 *
 * Geen module: er valt niets te beheren. Het adres staat in
 * `config/site.php` en ligt bewust vast, want een tikfout in een link
 * naar buiten is een dode knop op de voorpagina.
 *
 * Wat hier wordt nagelopen is dus vooral het omhulsel: dat het adres bij
 * de bezoeker aankomt, dat het een échte link naar LinkedIn is, en dat
 * de klant dit onderdeel net als elk ander kan verslepen en uitzetten.
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
     * Dit is geen formaliteit. Er is geen beheerscherm en dus geen
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
     * Het indelingsscherm belooft geen beheerscherm dat nooit komt.
     *
     * "Nog niet te beheren" is een belofte; bij dit onderdeel valt er
     * niets in te vullen, nu niet en later niet. Zie
     * PageSectionKey::teBeheren().
     */
    public function test_the_layout_screen_says_there_is_nothing_to_manage(): void
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
        $this->assertNull($regel['manageUrl']);
        $this->assertFalse($regel['manageable']);

        // En bij een onderdeel dat er wél een krijgt staat het andersom.
        $this->assertTrue(PageSectionKey::Werkwijze->teBeheren());
    }

    public function test_it_never_counts_as_empty(): void
    {
        /*
         * Er is geen teller voor dit onderdeel, en dat is de bedoeling:
         * de inhoud staat in de code en kan dus niet leeg zijn. Zonder
         * die afspraak zou het blok van de website vallen omdat niemand
         * eraan dacht een teller aan te melden.
         */
        $this->assertTrue(
            app(SectionContent::class)
                ->gevuld(PageSectionKey::Linkedin),
        );
    }
}
