<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\SectionHeading;
use App\Models\Service;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De kop boven de diensten.
 *
 * Wat hier wordt nagelopen is vooral de terugval tussen de talen, want
 * dat is de plek waar dit soort schermen stilletjes misgaat: één veld
 * dat wél terugvalt en één dat niet.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
class ServiceHeadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);

        // Zonder een online dienst staat het onderdeel niet op de
        // pagina, en dan komt de kop er ook niet -- terecht, maar dan
        // valt er hier niets te toetsen.
        Service::factory()->create();
    }

    /**
     * Alleen de rij van dít onderdeel.
     *
     * De koppen staan sinds het samenvoegen in één tabel, dus een kale
     * `SectionHeading::query()` raakt hier ook de kop van de tijdlijn en
     * die van de landingspagina. Zie docs/architecture/kopteksten.md.
     *
     * @return Builder<SectionHeading>
     */
    private function kopRij(): Builder
    {
        return SectionHeading::query()->where('section', PageSectionKey::Diensten);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $overschrijf
     */
    private function bewaar(array $overschrijf = []): TestResponse
    {
        return $this->actingAs($this->beheerder())->put(
            route('website.diensten.kop'),
            array_merge([
                'eyebrow_nl' => 'Diensten',
                'eyebrow_en' => 'Services',
                'title_nl' => 'Wat we doen',
                'title_en' => 'What we do',
                'intro_nl' => 'Drie dingen, en die goed.',
                'intro_en' => 'Three things, done properly.',
            ], $overschrijf),
        );
    }

    private function opDeSite(string $taal = 'nl'): mixed
    {
        $kop = null;

        $this->withSession(['locale' => $taal])
            ->get('/')
            ->assertInertia(function (AssertableInertia $page) use (&$kop) {
                $kop = $page->toArray()['props']['serviceHeading'];
            });

        return $kop;
    }

    public function test_the_three_texts_reach_the_website(): void
    {
        $this->bewaar()->assertSessionHasNoErrors();

        $this->assertSame([
            'opschrift' => 'Diensten',
            'titel' => 'Wat we doen',
            'inleiding' => 'Drie dingen, en die goed.',
        ], $this->opDeSite());
    }

    public function test_the_english_visitor_gets_the_english_text(): void
    {
        $this->bewaar();

        $this->assertSame([
            'opschrift' => 'Services',
            'titel' => 'What we do',
            'inleiding' => 'Three things, done properly.',
        ], $this->opDeSite('en'));
    }

    /**
     * De terugval, en waarom hij per veld verschilt.
     *
     * Het opschrift en de titel vallen terug op het Nederlands -- een
     * blok zonder kop is stuk. De zin eronder valt níet terug, want half
     * Nederlands op een Engelse pagina is slordiger dan geen zin.
     */
    public function test_missing_english_falls_back_per_field(): void
    {
        $this->bewaar([
            'eyebrow_en' => '',
            'title_en' => '',
            'intro_en' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            'opschrift' => 'Diensten',
            'titel' => 'Wat we doen',
            'inleiding' => null,
        ], $this->opDeSite('en'));
    }

    public function test_an_empty_optional_field_becomes_null_and_not_an_empty_string(): void
    {
        $this->bewaar(['intro_nl' => '', 'intro_en' => '']);

        $kop = $this->kopRij()->firstOrFail();

        $this->assertNull($kop->intro_nl);
        $this->assertNull($kop->intro_en);
    }

    public function test_the_eyebrow_and_the_title_are_required(): void
    {
        $this->bewaar(['eyebrow_nl' => '', 'title_nl' => ''])
            ->assertSessionHasErrors(['eyebrow_nl', 'title_nl']);

        // En er is niets gewijzigd.
        $this->assertSame('Diensten', $this->kopRij()->value('eyebrow_nl'));
    }

    public function test_saving_the_same_text_changes_nothing(): void
    {
        $this->bewaar();

        $gewijzigd = $this->kopRij()->value('updated_at');

        $this->travel(1)->minute();
        $this->bewaar()->assertSessionHasNoErrors();

        $this->assertEquals($gewijzigd, $this->kopRij()->value('updated_at'));
    }

    public function test_the_website_works_without_a_seeded_row(): void
    {
        /*
         * Een vergeten seeder mag geen blok zonder kop opleveren. De
         * standaardtekst uit SectionHeading::standaard() vangt dat op, en
         * die mag bij het tonen van de site niets wegschrijven.
         */
        $this->kopRij()->delete();

        $kop = $this->opDeSite();

        $this->assertSame('Diensten', $kop['opschrift']);
        $this->assertSame(0, $this->kopRij()->count());
    }

    public function test_seeding_again_keeps_what_the_owner_wrote(): void
    {
        $this->bewaar(['title_nl' => 'Mijn eigen kop']);

        $this->seed(SectionHeadingSeeder::class);

        $this->assertSame(1, $this->kopRij()->count());
        $this->assertSame('Mijn eigen kop', $this->kopRij()->value('title_nl'));
    }
}
