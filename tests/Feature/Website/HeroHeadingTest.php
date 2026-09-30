<?php

namespace Tests\Feature\Website;

use App\Enums\ActivityAction;
use App\Enums\PageSectionKey;
use App\Models\ActivityEntry;
use App\Models\SectionHeading;
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
 * De kop van de landingspagina: drie teksten die de klant beheert.
 *
 * Ze stonden hardgecodeerd in HeroSection.vue. Daarmee was het eerste dat
 * elke bezoeker leest het enige stuk van de voorpagina dat de eigenaar
 * niet kon aanpassen.
 *
 * Wat hier wordt nagelopen is vooral de terugval tussen de talen, want
 * dat is de plek waar dit soort schermen stilletjes misgaat: één veld dat
 * wél terugvalt en één dat niet, en dan staat er half Nederlands op een
 * Engelse pagina.
 *
 * Zie docs/architecture/modules/kop.md.
 */
class HeroHeadingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
    }

    /**
     * Alleen de rij van dít onderdeel.
     *
     * De koppen staan sinds het samenvoegen in één tabel, dus een kale
     * `SectionHeading::query()` raakt hier ook die van de diensten en de
     * tijdlijn. Zie docs/architecture/kopteksten.md.
     *
     * @return Builder<SectionHeading>
     */
    private function kopRij(): Builder
    {
        return SectionHeading::query()->where('section', PageSectionKey::Hero);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * De kop opslaan zoals het scherm hem stuurt.
     *
     * Alle zes de velden gaan altijd mee, ook de lege: het is één blok en
     * dus één opslag. Een test die er een weglaat toetst een verzoek dat
     * het scherm nooit doet.
     *
     * @param  array<string, mixed>  $overschrijf
     */
    private function bewaar(array $overschrijf = []): TestResponse
    {
        return $this->actingAs($this->beheerder())->put(
            route('website.kop.update'),
            array_merge([
                'eyebrow_nl' => 'IT-advies en realisatie',
                'eyebrow_en' => 'IT advice and delivery',
                'title_nl' => 'Techniek die werkt',
                'title_en' => 'Technology that works',
                'intro_nl' => 'Van advies tot beheer.',
                'intro_en' => 'From advice to management.',
            ], $overschrijf),
        );
    }

    /** De kop zoals een bezoeker hem krijgt. */
    private function opDeSite(string $taal = 'nl'): mixed
    {
        $kop = null;

        $this->withSession(['locale' => $taal])
            ->get('/')
            ->assertInertia(function (AssertableInertia $page) use (&$kop) {
                $kop = $page->toArray()['props']['heroHeading'];
            });

        return $kop;
    }

    public function test_a_guest_and_a_user_without_the_permission_are_stopped(): void
    {
        $this->get(route('website.kop.index'))->assertRedirect(route('login'));
        $this->put(route('website.kop.update'))->assertRedirect(route('login'));

        $gebruiker = User::factory()->create();

        $this->actingAs($gebruiker)
            ->get(route('website.kop.index'))
            ->assertForbidden();

        $this->actingAs($gebruiker)
            ->put(route('website.kop.update'))
            ->assertForbidden();
    }

    public function test_the_screen_shows_both_languages_apart(): void
    {
        // Dit is een bewerkscherm en geen weergave: de eigenaar vult
        // allebei de talen zelf in en moet dus zien wat er in allebei
        // staat.
        $this->actingAs($this->beheerder())
            ->get(route('website.kop.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Kop')
                ->where('kop.eyebrow_nl', 'IT-advies en realisatie')
                ->where('kop.eyebrow_en', 'IT advice and delivery')
                ->where('kop.title_nl', 'Techniek die doet wat je bedrijf nodig heeft.')
                ->where('kop.automatisch_vertaald', false)
                ->etc());
    }

    public function test_the_three_texts_reach_the_website(): void
    {
        $this->bewaar()->assertSessionHasNoErrors();

        $this->assertSame([
            'opschrift' => 'IT-advies en realisatie',
            'titel' => 'Techniek die werkt',
            'inleiding' => 'Van advies tot beheer.',
        ], $this->opDeSite());
    }

    public function test_the_english_visitor_gets_the_english_text(): void
    {
        $this->bewaar();

        $this->assertSame([
            'opschrift' => 'IT advice and delivery',
            'titel' => 'Technology that works',
            'inleiding' => 'From advice to management.',
        ], $this->opDeSite('en'));
    }

    /**
     * De terugval, en waarom hij per veld verschilt.
     *
     * Het opschrift en de titel vallen terug op het Nederlands: een
     * pagina zonder kop is stuk, en op een telefoon is het opschrift de
     * functie op het visitekaartje. De zin eronder valt níet terug, want
     * half Nederlands op een Engelse pagina is slordiger dan geen zin.
     */
    public function test_missing_english_falls_back_per_field(): void
    {
        $this->bewaar([
            'eyebrow_en' => '',
            'title_en' => '',
            'intro_en' => '',
        ])->assertSessionHasNoErrors();

        $this->assertSame([
            'opschrift' => 'IT-advies en realisatie',
            'titel' => 'Techniek die werkt',
            'inleiding' => null,
        ], $this->opDeSite('en'));
    }

    public function test_an_empty_optional_field_becomes_null_and_not_an_empty_string(): void
    {
        /*
         * Dat verschil bepaalt of de website er een zin onder zet. Een
         * lege string is "er staat iets, namelijk niets" en levert een
         * lege alinea op die de knoppen omlaag duwt.
         */
        $this->bewaar(['intro_nl' => '', 'intro_en' => '']);

        $kop = $this->kopRij()->firstOrFail();

        $this->assertNull($kop->intro_nl);
        $this->assertNull($kop->intro_en);
    }

    public function test_the_eyebrow_and_the_title_are_required(): void
    {
        $this->bewaar(['eyebrow_nl' => '', 'title_nl' => ''])
            ->assertSessionHasErrors(['eyebrow_nl', 'title_nl']);

        // En er is niets gewijzigd; een afgekeurd verzoek hoort de
        // bestaande tekst met rust te laten.
        $this->assertSame(
            'IT-advies en realisatie',
            $this->kopRij()->value('eyebrow_nl'),
        );
    }

    public function test_text_that_is_too_long_is_refused(): void
    {
        // De grenzen zijn die van de kolommen, en ze zijn krap met reden:
        // dit is een kop en geen alinea.
        $this->bewaar(['eyebrow_nl' => str_repeat('a', 61)])
            ->assertSessionHasErrors('eyebrow_nl');

        $this->bewaar(['title_nl' => str_repeat('a', 121)])
            ->assertSessionHasErrors('title_nl');

        $this->bewaar(['intro_nl' => str_repeat('a', 301)])
            ->assertSessionHasErrors('intro_nl');
    }

    public function test_saving_the_same_text_changes_nothing(): void
    {
        /*
         * Zonder deze controle komt er een regel in het
         * activiteitenlogboek en een melding "het is aangepast" terwijl
         * er niets anders is dan daarvoor.
         */
        $this->bewaar();

        $gewijzigd = $this->kopRij()->value('updated_at');

        $this->travel(1)->minute();
        $this->bewaar()->assertSessionHasNoErrors();

        $this->assertEquals($gewijzigd, $this->kopRij()->value('updated_at'));
    }

    public function test_the_machine_mark_is_set_and_cleared(): void
    {
        $this->bewaar(['automatisch_vertaald' => true]);

        $this->assertNotNull($this->kopRij()->value('machine_translated_at'));

        $this->bewaar([
            'title_en' => 'Iets dat ik zelf schreef',
            'automatisch_vertaald' => false,
        ]);

        $this->assertNull($this->kopRij()->value('machine_translated_at'));
    }

    public function test_a_change_is_written_to_the_activity_log(): void
    {
        // Dit is de eerste tekst die een bezoeker leest; verandert die,
        // dan hoort er later terug te vinden te zijn wanneer dat gebeurde
        // en wat er stond.
        $this->bewaar(['title_nl' => 'Iets heel anders']);

        $regel = ActivityEntry::query()
            ->ofAction(ActivityAction::Updated)
            ->latest('id')
            ->firstOrFail();

        $this->assertSame(SectionHeading::class, $regel->subject_type);

        /*
         * Het label zegt wélk onderdeel het was en niet welke tekst
         * erin stond. De titel verandert juist bij zo'n wijziging; die
         * als naam in het logboek zetten levert een regel op die
         * nergens meer op slaat. Zie SectionHeading::activityLabel().
         */
        $this->assertSame('Kop', $regel->subject_label);
    }

    public function test_the_website_works_without_a_seeded_row(): void
    {
        /*
         * Een vergeten seeder mag geen lege voorpagina opleveren. De
         * standaardtekst uit SectionHeading::standaard() vangt dat op,
         * en die mag bij het tonen van de site niets wegschrijven --
         * een GET hoort geen rij aan te maken.
         */
        $this->kopRij()->delete();

        $kop = $this->opDeSite();

        $this->assertSame('IT-advies en realisatie', $kop['opschrift']);
        $this->assertSame(0, $this->kopRij()->count());
    }

    public function test_seeding_again_keeps_what_the_owner_wrote(): void
    {
        $this->bewaar(['title_nl' => 'Mijn eigen kop']);

        $this->seed(SectionHeadingSeeder::class);

        $this->assertSame(1, $this->kopRij()->count());
        $this->assertSame('Mijn eigen kop', $this->kopRij()->value('title_nl'));
    }

    public function test_the_layout_screen_links_to_this_module(): void
    {
        // De kop stond op het indelingsscherm als "Nog niet te beheren".
        // Nu is er een scherm, dus hoort daar een link te staan.
        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sections.0.key', 'hero')
                ->where('sections.0.manageUrl', route('website.kop.index'))
                ->etc());
    }
}
