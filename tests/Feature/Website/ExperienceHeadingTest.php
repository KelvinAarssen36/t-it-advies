<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\Experience;
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
 * De kop boven de tijdlijn: de titel en de zin eronder.
 *
 * Die tekst stond in het Vue-component, en daarmee was hij het enige deel
 * van dat blok dat de klant níet kon aanpassen. Nu hoort hij bij de
 * module en wordt hij samen met de cijfers bewerkt -- op de website is het
 * tenslotte hetzelfde blok.
 *
 * Wat hier wordt nagelopen is vooral het gedrag rond de twee talen: dat de
 * titel terugvalt op het Nederlands en de inleiding niet, en dat het
 * merkje "automatisch vertaald" blijft staan of verdwijnt op de momenten
 * waarop dat hoort.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
class ExperienceHeadingTest extends TestCase
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
     * `SectionHeading::query()` raakt hier ook die van de landingspagina
     * en de diensten. Zie docs/architecture/kopteksten.md.
     *
     * @return Builder<SectionHeading>
     */
    private function kopRij(): Builder
    {
        return SectionHeading::query()->where('section', PageSectionKey::Ervaring);
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
            route('website.ervaring.kop'),
            array_merge([
                /*
                 * De cijfers gaan altijd mee: het scherm slaat de hele
                 * kop in één keer op, dus een verzoek zonder die sleutel
                 * bestaat daar niet. Een lege lijst betekent dat er geen
                 * cijfers boven de tijdlijn staan.
                 */
                'cijfers' => [],
                'title_nl' => 'Waar dit vandaan komt',
                'intro_nl' => 'De weg ernaartoe, van nu naar toen.',
            ], $overschrijf),
        );
    }

    public function test_a_guest_and_a_user_without_the_permission_are_stopped(): void
    {
        $this->put(route('website.ervaring.kop'), ['cijfers' => []])
            ->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->put(route('website.ervaring.kop'), ['cijfers' => []])
            ->assertForbidden();

        $this->assertSame(
            'Waar dit vandaan komt',
            $this->kopRij()->value('title_nl'),
        );
    }

    public function test_the_owner_can_change_both_languages(): void
    {
        $this->bewaar([
            'title_nl' => 'Mijn loopbaan',
            'title_en' => 'My career',
            'intro_nl' => 'Van nu naar toen.',
            'intro_en' => 'From now back to then.',
        ])->assertSessionHasNoErrors();

        $kop = $this->kopRij()->firstOrFail();

        $this->assertSame('Mijn loopbaan', $kop->title_nl);
        $this->assertSame('My career', $kop->title_en);
        $this->assertSame('From now back to then.', $kop->intro_en);
    }

    public function test_a_title_is_required(): void
    {
        $this->bewaar(['title_nl' => ''])->assertSessionHasErrors('title_nl');

        $this->assertSame(
            'Waar dit vandaan komt',
            $this->kopRij()->value('title_nl'),
        );
    }

    public function test_an_empty_intro_becomes_null_and_not_an_empty_string(): void
    {
        /*
         * De website beslist met `filled()` of de zin getoond wordt. Een
         * lege tekst zou daar als "er staat iets" tellen en een lege regel
         * onder de titel opleveren.
         */
        $this->bewaar(['intro_nl' => '', 'intro_en' => ''])
            ->assertSessionHasNoErrors();

        $kop = $this->kopRij()->firstOrFail();

        $this->assertNull($kop->intro_nl);
        $this->assertNull($kop->intro_en);
    }

    public function test_a_title_that_is_far_too_long_is_refused(): void
    {
        // Dit is een kop en geen alinea: op een telefoon breekt een titel
        // van tweehonderd tekens het ontwerp.
        $this->bewaar(['title_nl' => str_repeat('a', 200)])
            ->assertSessionHasErrors('title_nl');
    }

    public function test_the_visitor_sees_the_managed_text(): void
    {
        Experience::factory()->create();

        $this->bewaar(['title_nl' => 'Mijn loopbaan']);

        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                ->where('experienceHeading.titel', 'Mijn loopbaan'),
        );
    }

    public function test_the_title_falls_back_to_dutch_but_the_intro_does_not(): void
    {
        Experience::factory()->create();

        $this->bewaar([
            'title_nl' => 'Mijn loopbaan',
            'title_en' => '',
            'intro_nl' => 'Van nu naar toen.',
            'intro_en' => '',
        ]);

        $this->withSession(['locale' => 'en'])->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page
                // Een blok zonder kop is stuk, dus die valt terug.
                ->where('experienceHeading.titel', 'Mijn loopbaan')
                // Half Nederlands op een Engelse pagina is slordiger dan
                // geen zin, dus die valt niet terug.
                ->where('experienceHeading.inleiding', null),
        );
    }

    public function test_the_heading_is_absent_when_the_section_is(): void
    {
        // Geen ervaringen, dus geen tijdlijn -- en dan ook geen kop erboven.
        $this->get('/')->assertInertia(
            fn (AssertableInertia $page) => $page->where('experienceHeading', null),
        );
    }

    public function test_a_machine_translation_is_marked_as_such(): void
    {
        $this->bewaar([
            'title_en' => 'My career',
            'automatisch_vertaald' => true,
        ]);

        $this->assertNotNull(
            $this->kopRij()->value('machine_translated_at'),
        );
    }

    public function test_editing_the_english_yourself_clears_the_mark(): void
    {
        $this->bewaar(['title_en' => 'My career', 'automatisch_vertaald' => true]);

        $this->bewaar(['title_en' => 'My own career', 'automatisch_vertaald' => false]);

        $this->assertNull(
            $this->kopRij()->value('machine_translated_at'),
        );
    }

    public function test_seeding_again_keeps_what_the_owner_wrote(): void
    {
        /*
         * Dezelfde afspraak als bij de cijfers en de indeling: een deploy
         * mag nooit de tekst van de klant terugzetten. Dat ziet hij op
         * zijn eigen voorpagina.
         */
        $this->bewaar(['title_nl' => 'Mijn loopbaan']);

        $this->seed(SectionHeadingSeeder::class);

        $this->assertSame(
            'Mijn loopbaan',
            $this->kopRij()->value('title_nl'),
        );
    }
}
