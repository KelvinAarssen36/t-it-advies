<?php

namespace Tests\Feature\Website;

use App\Models\Certificate;
use App\Models\Education;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Online, offline en de volgorde van de certificaten.
 *
 * Waar het hier om gaat: wat de eigenaar uitzet hoort **nergens** meer
 * te staan -- niet op de website, en niet in de telling die bepaalt of
 * het onderdeel nog inhoud heeft. Die twee uit elkaar laten lopen levert
 * een indelingsscherm op dat zegt dat er iets staat terwijl de bezoeker
 * niets ziet.
 *
 * Zie docs/architecture/modules/certificaten.md.
 */
class CertificatePublishingTest extends TestCase
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

    /** De namen van de certificaten zoals de website ze meestuurt. */
    private function opDeSite(): array
    {
        $namen = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$namen) {
            foreach ($page->toArray()['props']['certificates'] as $rij) {
                $namen[] = $rij['naam'];
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

    public function test_the_switch_puts_a_certificate_online_and_offline(): void
    {
        $certificaat = Certificate::factory()->offline()->create();

        $this->actingAs($this->beheerder())->patch(
            route('website.certificaten.online', $certificaat),
            ['published' => true],
        );

        $this->assertTrue($certificaat->fresh()?->published);

        $this->actingAs($this->beheerder())->patch(
            route('website.certificaten.online', $certificaat),
            ['published' => false],
        );

        $this->assertFalse($certificaat->fresh()?->published);
    }

    public function test_what_is_offline_is_not_on_the_website(): void
    {
        Certificate::factory()->create(['title_nl' => 'Zichtbaar', 'position' => 1]);
        Certificate::factory()->offline()->create(['title_nl' => 'Verborgen', 'position' => 2]);

        $this->assertSame(['Zichtbaar'], $this->opDeSite());
    }

    public function test_the_whole_section_disappears_when_everything_is_offline(): void
    {
        Certificate::factory()->offline()->create();

        // Een kopje met niets eronder is slordiger dan geen kopje.
        $this->assertNotContains('certificaten', $this->sectiesOpDeSite());
    }

    public function test_the_section_comes_back_as_soon_as_there_is_one_online(): void
    {
        Certificate::factory()->create();

        $this->assertContains('certificaten', $this->sectiesOpDeSite());
    }

    /**
     * Eén opleiding is ook inhoud.
     *
     * De twee lijsten hangen aan hetzelfde onderdeel, dus een blok met
     * alleen een opleiding erin is niet leeg. Zou de teller alleen de
     * certificaten tellen, dan verdwijnt die opleiding van de website
     * zonder dat iemand begrijpt waarom.
     */
    public function test_an_education_on_its_own_keeps_the_section_alive(): void
    {
        Education::factory()->create();

        $this->assertContains('certificaten', $this->sectiesOpDeSite());
    }

    public function test_an_offline_education_does_not_count(): void
    {
        Education::factory()->offline()->create();

        $this->assertNotContains('certificaten', $this->sectiesOpDeSite());
    }

    public function test_the_certificates_are_in_the_order_the_owner_set(): void
    {
        Certificate::factory()->create(['title_nl' => 'Derde', 'position' => 3]);
        Certificate::factory()->create(['title_nl' => 'Eerste', 'position' => 1]);
        Certificate::factory()->create(['title_nl' => 'Tweede', 'position' => 2]);

        $this->assertSame(['Eerste', 'Tweede', 'Derde'], $this->opDeSite());
    }

    public function test_the_order_can_be_changed(): void
    {
        $een = Certificate::factory()->create(['title_nl' => 'Een', 'position' => 1]);
        $twee = Certificate::factory()->create(['title_nl' => 'Twee', 'position' => 2]);

        $this->actingAs($this->beheerder())->put(
            route('website.certificaten.volgorde'),
            ['certificaten' => [$twee->id, $een->id]],
        )->assertSessionHasNoErrors();

        $this->assertSame(['Twee', 'Een'], $this->opDeSite());
    }

    /**
     * Een halve lijst wordt geweigerd.
     *
     * Zou een verzoek met twee van de drie ids doorgaan, dan krijgen die
     * twee positie 1 en 2 en botsen ze met de derde.
     */
    public function test_an_incomplete_order_is_refused(): void
    {
        $een = Certificate::factory()->create(['position' => 1]);
        Certificate::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())->put(
            route('website.certificaten.volgorde'),
            ['certificaten' => [$een->id]],
        );

        $this->assertSame(1, (int) $een->fresh()?->position);
    }

    public function test_an_unknown_id_in_the_order_is_refused(): void
    {
        $een = Certificate::factory()->create(['position' => 1]);

        $this->actingAs($this->beheerder())->put(
            route('website.certificaten.volgorde'),
            ['certificaten' => [$een->id, 9999]],
        )->assertSessionHasErrors('certificaten.1');
    }

    public function test_the_layout_screen_counts_only_what_is_online(): void
    {
        Certificate::factory()->count(2)->create();
        Certificate::factory()->offline()->create();
        Education::factory()->create();

        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $rijen = collect($page->toArray()['props']['sections']);
                $rij = $rijen->firstWhere('key', 'certificaten');

                // Twee certificaten plus één opleiding; wat offline
                // staat telt niet mee.
                $this->assertSame(3, $rij['count']);
                $this->assertTrue($rij['filled']);
            });
    }

    public function test_the_layout_screen_flags_an_empty_section(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $rijen = collect($page->toArray()['props']['sections']);
                $rij = $rijen->firstWhere('key', 'certificaten');

                $this->assertSame(0, $rij['count']);
                $this->assertFalse($rij['filled']);
                $this->assertFalse($rij['live']);

                // En er is een scherm om het op te lossen.
                $this->assertNotNull($rij['manageUrl']);
            });
    }
}
