<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\CoreFact;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Wat er van de kerngegevens op de website terechtkomt.
 *
 * Drie dingen: offline staat er niet op, een lege strook valt helemaal
 * weg, en de volgorde van de eigenaar wordt aangehouden.
 *
 * **Dat laatste is hier geen detail.** Op een telefoon staan de vakken
 * onder elkaar, dus wat bovenaan staat is wat iemand met een klein scherm
 * als eerste leest.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */
class CoreFactPublishingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    public function test_the_strip_is_on_the_landing_page(): void
    {
        CoreFact::factory()->create(['label_nl' => 'Beschikbaar']);

        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('coreFacts', 1)
                ->where('coreFacts.0.label', 'Beschikbaar')
                ->has('coreFactsHeading')
                ->where(
                    'sections',
                    fn (Collection $secties) => in_array(
                        PageSectionKey::Kerngegevens->value,
                        $secties->all(),
                        true,
                    ),
                )
            );
    }

    public function test_an_offline_core_fact_is_not_on_the_site(): void
    {
        CoreFact::factory()->create(['label_nl' => 'Zichtbaar']);
        CoreFact::factory()->offline()->create(['label_nl' => 'Verborgen']);

        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('coreFacts', 1)
                ->where('coreFacts.0.label', 'Zichtbaar')
            );
    }

    /**
     * Staat alles uit, dan valt het hele onderdeel weg.
     *
     * Dat regelt de teller in AppServiceProvider: een kopje met niets
     * eronder is slordiger dan geen kopje.
     */
    public function test_an_empty_strip_disappears_entirely(): void
    {
        CoreFact::factory()->offline()->create();

        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    'sections',
                    fn (Collection $secties) => ! in_array(
                        PageSectionKey::Kerngegevens->value,
                        $secties->all(),
                        true,
                    ),
                )
            );
    }

    public function test_the_order_of_the_owner_is_followed(): void
    {
        CoreFact::factory()->create(['label_nl' => 'Derde', 'position' => 3]);
        CoreFact::factory()->create(['label_nl' => 'Eerste', 'position' => 1]);
        CoreFact::factory()->create(['label_nl' => 'Tweede', 'position' => 2]);

        $this->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('coreFacts.0.label', 'Eerste')
                ->where('coreFacts.1.label', 'Tweede')
                ->where('coreFacts.2.label', 'Derde')
            );
    }

    /* --- De terugval tussen de talen ------------------------------------- */

    /**
     * Het label en de waarde vallen terug, de toelichting niet.
     *
     * Een waarde zonder naam zegt niets, en een nummer is in beide talen
     * hetzelfde -- dus die twee vallen terug op het Nederlands. Een
     * Nederlandse toelichting tussen Engelse tekst valt juist méér op dan
     * een ontbrekende toelichting, dus die wordt weggelaten.
     */
    public function test_the_fallback_differs_per_field(): void
    {
        CoreFact::factory()->create([
            'label_nl' => 'KvK',
            'label_en' => null,
            'value_nl' => '12 34 56 78',
            'value_en' => null,
            'note_nl' => 'Alleen in het Nederlands.',
            'note_en' => null,
        ]);

        /*
         * De taal komt uit de sessie en niet uit het adres: er is geen
         * `/en`. `SetLocale` leest achtereenvolgens het profiel, de
         * sessie en de browser; zie docs/architecture/vertalingen.md.
         */
        $this->withSession(['locale' => 'en'])->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('coreFacts.0.label', 'KvK')
                ->where('coreFacts.0.waarde', '12 34 56 78')
                ->where('coreFacts.0.notitie', null)
            );
    }

    public function test_the_english_version_is_used_when_it_is_there(): void
    {
        CoreFact::factory()->create([
            'label_nl' => 'Beschikbaar',
            'label_en' => 'Available',
            'value_nl' => 'Vanaf januari',
            'value_en' => 'From January',
            'note_nl' => 'Twee dagen per week.',
            'note_en' => 'Two days a week.',
        ]);

        $this->withSession(['locale' => 'en'])->get('/')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('coreFacts.0.label', 'Available')
                ->where('coreFacts.0.waarde', 'From January')
                ->where('coreFacts.0.notitie', 'Two days a week.')
            );
    }
}
