<?php

namespace Tests\Feature\Website;

use App\Enums\ExperienceStatKey;
use App\Enums\ExperienceStatModus;
use App\Models\Experience;
use App\Models\ExperienceStat;
use App\Models\User;
use Database\Seeders\ExperienceStatSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De cijfers boven de tijdlijn.
 *
 * Ze waren drie vaste getallen waarvan "leeg" betekende: reken het uit.
 * Nu is het een lijst die de klant beheert -- hij bepaalt hoeveel het er
 * zijn, hoe ze heten, en per stuk of wij het uitrekenen, hij zelf een
 * getal invult, of het er niet staat.
 *
 * Wat hier wordt nagelopen is vooral waar die drie standen elkaar raken:
 * een eigen cijfer kunnen wij niet uitrekenen, een verborgen cijfer hoort
 * niet op de site, en van een soort dat wij tellen mag er maar één zijn.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class ExperienceStatsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(ExperienceStatSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * Eén cijfer zoals het scherm het stuurt.
     *
     * @param  array<string, mixed>  $overschrijf
     * @return array<string, mixed>
     */
    private function cijfer(array $overschrijf = []): array
    {
        return array_merge([
            'id' => null,
            'key' => ExperienceStatKey::Years->value,
            'label_nl' => null,
            'label_en' => null,
            'modus' => ExperienceStatModus::Automatisch->value,
            'waarde' => null,
        ], $overschrijf);
    }

    /**
     * @param  array<int, array<string, mixed>>  $cijfers
     */
    private function bewaar(array $cijfers): TestResponse
    {
        // De titel gaat mee, want deze route bewaart de hele kop boven de
        // tijdlijn: de tekst én de cijfers. Zonder hem zou elke test hier
        // op een validatiefout stuklopen in plaats van op zijn onderwerp.
        return $this->actingAs($this->beheerder())->put(
            route('website.ervaring.kop'),
            ['cijfers' => $cijfers, 'title_nl' => 'Waar dit vandaan komt'],
        );
    }

    /**
     * De cijfers zoals een bezoeker ze krijgt.
     *
     * @return array<int, array{waarde: int, label: string}>
     */
    private function opDeSite(): array
    {
        $cijfers = [];

        $this->get('/')->assertInertia(function (AssertableInertia $page) use (&$cijfers) {
            $cijfers = $page->toArray()['props']['experienceSummary'];
        });

        return $cijfers;
    }

    public function test_a_guest_and_a_user_without_the_permission_are_stopped(): void
    {
        $this->put(route('website.ervaring.kop'), ['cijfers' => []])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->put(route('website.ervaring.kop'), ['cijfers' => []])
            ->assertForbidden();

        $this->assertSame(3, ExperienceStat::query()->count());
    }

    public function test_the_route_is_not_swallowed_by_the_detail_page(): void
    {
        /*
         * `PUT website/ervaring/kop` en `PUT website/ervaring/{id}` zijn
         * allebei een PUT op hetzelfde patroon, en de eerste die past
         * wint. Staat de volgorde in routes/website.php ooit andersom,
         * dan gaat dit verzoek naar `update()` en faalt het op een
         * ontbrekende functietitel -- zonder dat iemand begrijpt waarom.
         */
        $this->bewaar([])->assertSessionHasNoErrors();

        $this->assertSame(0, ExperienceStat::query()->count());
    }

    public function test_automatic_is_counted_from_the_timeline(): void
    {
        Experience::factory()->count(2)->create(['organisation' => 'Eni']);

        $cijfers = $this->opDeSite();

        $this->assertSame(2, $this->waardeVan($cijfers, 'functies'));
        $this->assertSame(1, $this->waardeVan($cijfers, 'organisaties'));
    }

    public function test_a_number_of_your_own_wins_from_the_calculation(): void
    {
        Experience::factory()->count(2)->create();

        $this->bewaar([
            $this->cijfer([
                'key' => ExperienceStatKey::Roles->value,
                'modus' => ExperienceStatModus::Eigen->value,
                'waarde' => 40,
            ]),
        ])->assertSessionHasNoErrors();

        $this->assertSame(40, $this->opDeSite()[0]['waarde']);
    }

    public function test_back_to_automatic_brings_the_calculation_back(): void
    {
        Experience::factory()->count(2)->create();

        $this->bewaar([
            $this->cijfer([
                'key' => ExperienceStatKey::Roles->value,
                'modus' => ExperienceStatModus::Eigen->value,
                'waarde' => 40,
            ]),
        ]);

        $id = ExperienceStat::query()->value('id');

        $this->bewaar([
            $this->cijfer([
                'id' => $id,
                'key' => ExperienceStatKey::Roles->value,
                'modus' => ExperienceStatModus::Automatisch->value,
                'waarde' => 40,
            ]),
        ])->assertSessionHasNoErrors();

        $this->assertSame(2, $this->opDeSite()[0]['waarde']);
    }

    public function test_a_hidden_number_is_not_on_the_website(): void
    {
        /*
         * Dit is waarvoor de derde stand bestaat. Eerst betekende een leeg
         * veld "reken het uit", en dus kon de klant een cijfer helemaal
         * niet weglaten.
         */
        Experience::factory()->count(2)->create();

        $this->bewaar([
            $this->cijfer([
                'key' => ExperienceStatKey::Roles->value,
                'modus' => ExperienceStatModus::Verborgen->value,
            ]),
            $this->cijfer(['key' => ExperienceStatKey::Organisations->value]),
        ])->assertSessionHasNoErrors();

        $labels = array_column($this->opDeSite(), 'label');

        $this->assertNotContains('functies', $labels);
        $this->assertContains('organisaties', $labels);
    }

    public function test_the_owner_can_rename_a_number(): void
    {
        Experience::factory()->count(2)->create();

        $this->bewaar([
            $this->cijfer([
                'key' => ExperienceStatKey::Roles->value,
                'label_nl' => 'opdrachten',
            ]),
        ])->assertSessionHasNoErrors();

        $this->assertSame('opdrachten', $this->opDeSite()[0]['label']);
    }

    public function test_an_empty_name_falls_back_to_the_standard_word(): void
    {
        /*
         * Dat is waarom leeg laten hier de betere keuze is dan het woord
         * overtypen: het standaardwoord is vertaald, dus het blijft ook
         * op de Engelse site kloppen.
         */
        Experience::factory()->count(2)->create();

        $this->bewaar([
            $this->cijfer([
                'key' => ExperienceStatKey::Roles->value,
                'label_nl' => null,
            ]),
        ]);

        $this->assertSame('functies', $this->opDeSite()[0]['label']);

        $this->withSession(['locale' => 'en'])->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('experienceSummary.0.label', 'roles'),
        );
    }

    public function test_a_custom_number_cannot_be_automatic(): void
    {
        // Wij kunnen "12 certificeringen" niet uit de tijdlijn tellen, dus
        // automatisch bestaat daar niet.
        $this->bewaar([
            $this->cijfer([
                'key' => ExperienceStatKey::Eigen->value,
                'modus' => ExperienceStatModus::Automatisch->value,
            ]),
        ])->assertSessionHasErrors('cijfers.0.modus');
    }

    public function test_a_number_of_your_own_needs_a_number(): void
    {
        $this->bewaar([
            $this->cijfer([
                'modus' => ExperienceStatModus::Eigen->value,
                'waarde' => null,
            ]),
        ])->assertSessionHasErrors('cijfers.0.waarde');
    }

    public function test_the_same_kind_cannot_be_there_twice(): void
    {
        $this->bewaar([
            $this->cijfer(['key' => ExperienceStatKey::Roles->value]),
            $this->cijfer(['key' => ExperienceStatKey::Roles->value]),
        ])->assertSessionHasErrors('cijfers.1.key');
    }

    public function test_more_than_four_is_refused(): void
    {
        $eigen = $this->cijfer([
            'key' => ExperienceStatKey::Eigen->value,
            'modus' => ExperienceStatModus::Eigen->value,
            'waarde' => 5,
        ]);

        $this->bewaar([
            $this->cijfer(['key' => ExperienceStatKey::Years->value]),
            $this->cijfer(['key' => ExperienceStatKey::Roles->value]),
            $this->cijfer(['key' => ExperienceStatKey::Organisations->value]),
            $eigen,
            $eigen,
        ])->assertSessionHasErrors('cijfers');
    }

    public function test_four_custom_numbers_are_allowed(): void
    {
        // Van een eigen cijfer mogen er wél meer: dat is hoe de klant aan
        // getallen komt die niets met de tijdlijn te maken hebben.
        $eigen = $this->cijfer([
            'key' => ExperienceStatKey::Eigen->value,
            'modus' => ExperienceStatModus::Eigen->value,
            'waarde' => 5,
        ]);

        $this->bewaar([$eigen, $eigen, $eigen, $eigen])
            ->assertSessionHasNoErrors();

        $this->assertSame(4, ExperienceStat::query()->count());
    }

    public function test_a_nonsense_value_is_refused_and_nothing_changes(): void
    {
        $this->bewaar([
            $this->cijfer([
                'modus' => ExperienceStatModus::Eigen->value,
                'waarde' => 99999,
            ]),
        ])->assertSessionHasErrors('cijfers.0.waarde');

        $this->assertSame(3, ExperienceStat::query()->count());
    }

    public function test_a_number_that_is_left_out_is_removed(): void
    {
        $this->bewaar([$this->cijfer()])->assertSessionHasNoErrors();

        $this->assertSame(1, ExperienceStat::query()->count());
    }

    /**
     * Wat wij zouden tellen hangt aan het **soort** en niet aan de rij.
     *
     * **Dit is de test bij een echte fout.** Het getal stond eerst in de
     * rij: elk cijfer droeg mee wat er voor zíjn soort geteld zou worden.
     * Koos de eigenaar in het venster een ander soort, dan wist het scherm
     * niet wat daarbij hoort en zette het er nul neer -- "nu zouden wij er
     * 0 tellen" boven een tijdlijn van vijfendertig jaar.
     *
     * Staat het bij het soort, dan kan het scherm het bij elke keuze
     * opzoeken, ook bij een keuze die nog niet is opgeslagen. Vandaar dat
     * deze test alle soorten langsloopt en niet alleen de opgeslagen rij.
     */
    public function test_every_kind_carries_what_we_would_count(): void
    {
        Experience::factory()->count(3)->create(['organisation' => 'Eni']);

        $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijferKeuzes.soort.1.value', 'functies')
                ->where('cijferKeuzes.soort.1.berekend', 3)
                ->where('cijferKeuzes.soort.1.berekenbaar', true)
                ->where('cijferKeuzes.soort.2.value', 'organisaties')
                ->where('cijferKeuzes.soort.2.berekend', 1)
                // Een eigen cijfer kunnen wij niet tellen, en dan is null
                // het eerlijke antwoord. Nul zou suggereren dat we het
                // geprobeerd hebben.
                ->where('cijferKeuzes.soort.3.value', 'eigen')
                ->where('cijferKeuzes.soort.3.berekend', null)
                ->where('cijferKeuzes.soort.3.berekenbaar', false)
                ->etc());
    }

    /**
     * En dus staat het niet meer in de rij zelf.
     *
     * Zou het daar terugkomen, dan is er weer een tweede kopie die
     * achterloopt zodra de eigenaar het soort wisselt. Deze test is er om
     * dat te laten opvallen.
     */
    public function test_a_row_carries_only_what_the_owner_set(): void
    {
        Experience::factory()->count(3)->create();

        $this->actingAs($this->beheerder())
            ->get(route('website.ervaring.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('cijfers.0', fn (AssertableInertia $rij) => $rij
                    ->hasAll(['id', 'key', 'label_nl', 'label_en', 'modus', 'waarde'])
                    ->missing('berekend')
                    ->missing('standaard_label')
                    ->missing('berekenbaar'))
                ->etc());
    }

    public function test_there_are_no_numbers_without_experiences(): void
    {
        // Nullen boven een lege lijst is erger dan geen cijfers.
        $this->assertSame([], $this->opDeSite());
    }

    public function test_seeding_again_keeps_what_the_owner_did(): void
    {
        $this->bewaar([$this->cijfer(['label_nl' => 'lentes'])]);

        $this->seed(ExperienceStatSeeder::class);

        $this->assertSame(1, ExperienceStat::query()->count());
        $this->assertSame('lentes', ExperienceStat::query()->value('label_nl'));
    }

    /**
     * @param  array<int, array{waarde: int, label: string}>  $cijfers
     */
    private function waardeVan(array $cijfers, string $label): ?int
    {
        foreach ($cijfers as $cijfer) {
            if ($cijfer['label'] === $label) {
                return $cijfer['waarde'];
            }
        }

        return null;
    }
}
