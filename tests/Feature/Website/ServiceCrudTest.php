<?php

namespace Tests\Feature\Website;

use App\Enums\ActivityAction;
use App\Enums\PageSectionKey;
use App\Models\ActivityEntry;
use App\Models\Service;
use App\Models\ServicePoint;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De diensten: aanmaken, wijzigen, verwijderen.
 *
 * De lijst met wat een CRUD moet afdekken staat in
 * docs/development/testen.md; dit bestand loopt hem af. De
 * expertisepunten hebben hun eigen bestand, want daar zit genoeg eigen
 * gedrag in om het hier onleesbaar te maken.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
class ServiceCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * Een volledig geldig formulier, waarvan je stukjes kunt vervangen.
     *
     * @param  array<string, mixed>  $anders
     * @return array<string, mixed>
     */
    private function invoer(array $anders = []): array
    {
        return array_merge([
            'icon' => 'advies',
            'published' => true,
            'title_nl' => 'Advies',
            'title_en' => 'Advice',
            'summary_nl' => 'Meedenken over wat er nodig is.',
            'summary_en' => 'Thinking along about what is needed.',
            'body_nl' => 'Het hele verhaal.',
            'body_en' => 'The whole story.',
            'punten' => [
                ['text_nl' => 'inventarisatie', 'text_en' => 'inventory'],
            ],
        ], $anders);
    }

    /**
     * @param  array<string, mixed>  $anders
     */
    private function maak(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())
            ->post(route('website.diensten.store'), $this->invoer($anders));
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $dienst = Service::factory()->create();

        $this->get(route('website.diensten.index'))->assertRedirect(route('login'));
        $this->post(route('website.diensten.store'))->assertRedirect(route('login'));
        $this->put(route('website.diensten.update', $dienst))->assertRedirect(route('login'));
        $this->put(route('website.diensten.kop'))->assertRedirect(route('login'));
        $this->put(route('website.diensten.volgorde'))->assertRedirect(route('login'));
        $this->patch(route('website.diensten.online', $dienst))->assertRedirect(route('login'));
        $this->delete(route('website.diensten.destroy', $dienst))->assertRedirect(route('login'));

        // En er is niets gebeurd.
        $this->assertSame(1, Service::query()->count());
    }

    public function test_every_route_needs_the_permission(): void
    {
        $dienst = Service::factory()->create();
        $gebruiker = User::factory()->create();

        $this->actingAs($gebruiker)->get(route('website.diensten.index'))->assertForbidden();
        $this->actingAs($gebruiker)->post(route('website.diensten.store'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.diensten.update', $dienst))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.diensten.kop'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.diensten.volgorde'))->assertForbidden();
        $this->actingAs($gebruiker)->patch(route('website.diensten.online', $dienst))->assertForbidden();
        $this->actingAs($gebruiker)->delete(route('website.diensten.destroy', $dienst))->assertForbidden();

        $this->assertSame(1, Service::query()->count());
    }

    public function test_the_screen_lists_the_services(): void
    {
        Service::factory()->count(3)->create();

        $this->actingAs($this->beheerder())
            ->get(route('website.diensten.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Diensten')
                ->has('items', 3)
                ->has('opties.icon')
                ->where('opties.puntenMaximum', Service::PUNTEN_MAXIMUM)
                ->where('leeg', false)
                ->where('kanVertalen', false)
                ->etc());
    }

    public function test_creating_stores_both_languages(): void
    {
        $this->maak()->assertSessionHasNoErrors();

        $dienst = Service::query()->firstOrFail();

        $this->assertSame('Advies', $dienst->title_nl);
        $this->assertSame('Advice', $dienst->title_en);
        $this->assertSame('Meedenken over wat er nodig is.', $dienst->summary_nl);
        $this->assertSame('Thinking along about what is needed.', $dienst->summary_en);
        $this->assertSame('Het hele verhaal.', $dienst->body_nl);
        $this->assertSame('The whole story.', $dienst->body_en);
    }

    public function test_a_new_service_goes_to_the_end_of_the_row(): void
    {
        // Waar hij komt te staan bepaalt de eigenaar daarna met slepen;
        // hem ergens tussen zetten zou een keuze zijn die wij maken.
        Service::factory()->create(['position' => 7]);

        $this->maak();

        $this->assertSame(
            8,
            Service::query()->orderByDesc('id')->value('position'),
        );
    }

    public function test_an_empty_optional_field_becomes_null_and_not_an_empty_string(): void
    {
        /*
         * Dat verschil bepaalt of de website er iets neerzet. Een lege
         * uitgebreide tekst betekent: geen venster, en dus geen "Lees
         * meer" op de kaart.
         */
        $this->maak([
            'title_en' => '',
            'summary_en' => '   ',
            'body_nl' => '',
            'body_en' => '',
        ])->assertSessionHasNoErrors();

        $dienst = Service::query()->firstOrFail();

        $this->assertNull($dienst->title_en);
        $this->assertNull($dienst->summary_en);
        $this->assertNull($dienst->body_nl);
        $this->assertNull($dienst->body_en);
    }

    public function test_updating_changes_only_that_service(): void
    {
        $dienst = Service::factory()->create(['title_nl' => 'Oud']);
        $andere = Service::factory()->create(['title_nl' => 'Blijft']);

        $this->actingAs($this->beheerder())
            ->put(
                route('website.diensten.update', $dienst),
                $this->invoer(['title_nl' => 'Nieuw']),
            )
            ->assertSessionHasNoErrors();

        $this->assertSame('Nieuw', $dienst->fresh()?->title_nl);
        $this->assertSame('Blijft', $andere->fresh()?->title_nl);
    }

    public function test_saving_without_a_change_says_so(): void
    {
        $this->maak();

        $dienst = Service::query()->firstOrFail();

        /*
         * Alleen de regels over een dienst tellen. Elke `beheerder()`
         * maakt ook een gebruiker aan, en die komt net zo goed in het
         * logboek -- zonder dit filter telt deze test die mee en gaat
         * hij om een reden die niets met diensten te maken heeft.
         */
        $vanDiensten = fn () => ActivityEntry::query()
            ->where('subject_type', Service::class)
            ->count();

        $aantal = $vanDiensten();

        $this->actingAs($this->beheerder())
            ->put(route('website.diensten.update', $dienst), $this->invoer())
            ->assertSessionHasNoErrors();

        // Geen regel in het logboek, want er is niets veranderd.
        $this->assertSame($aantal, $vanDiensten());
    }

    public function test_deleting_removes_only_that_service_and_its_points(): void
    {
        $this->maak();

        $dienst = Service::query()->firstOrFail();
        $andere = Service::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.diensten.destroy', $dienst));

        $this->assertDatabaseMissing('services', ['id' => $dienst->id]);
        $this->assertDatabaseHas('services', ['id' => $andere->id]);

        // De punten gaan mee; ze bestaan niet los van hun dienst.
        $this->assertSame(
            0,
            ServicePoint::query()->where('service_id', $dienst->id)->count(),
        );
    }

    public function test_invalid_input_is_refused_and_nothing_is_stored(): void
    {
        $this->maak([
            'icon' => '',
            'title_nl' => '',
            'summary_nl' => '',
        ])->assertSessionHasErrors(['icon', 'title_nl', 'summary_nl']);

        $this->assertSame(0, Service::query()->count());
    }

    public function test_an_unknown_icon_is_refused(): void
    {
        $this->maak(['icon' => 'raket'])->assertSessionHasErrors('icon');

        $this->assertSame(0, Service::query()->count());
    }

    public function test_text_that_is_far_too_long_is_refused(): void
    {
        $this->maak(['title_nl' => str_repeat('a', 81)])
            ->assertSessionHasErrors('title_nl');

        $this->maak(['summary_nl' => str_repeat('a', 301)])
            ->assertSessionHasErrors('summary_nl');

        $this->maak(['body_nl' => str_repeat('a', 5001)])
            ->assertSessionHasErrors('body_nl');

        $this->assertSame(0, Service::query()->count());
    }

    public function test_a_long_story_is_stored_whole(): void
    {
        // De grens is 5000; dit bewijst dat er onderweg niets wordt
        // afgekapt. Zonder deze test valt een stille truncatie pas op
        // als de klant zijn eigen tekst mist.
        $verhaal = str_repeat('Een zin die ergens over gaat. ', 150);

        $this->maak(['body_nl' => $verhaal])->assertSessionHasNoErrors();

        $this->assertSame(
            trim($verhaal),
            Service::query()->value('body_nl'),
        );
    }

    public function test_every_change_ends_up_in_the_activity_log(): void
    {
        $this->maak();

        $dienst = Service::query()->firstOrFail();

        $this->actingAs($this->beheerder())->put(
            route('website.diensten.update', $dienst),
            $this->invoer(['title_nl' => 'Iets anders']),
        );

        $this->actingAs($this->beheerder())
            ->delete(route('website.diensten.destroy', $dienst));

        $this->assertSame(
            3,
            ActivityEntry::query()->where('subject_type', Service::class)->count(),
        );

        $verwijderd = ActivityEntry::query()
            ->ofAction(ActivityAction::Deleted)
            ->latest('id')
            ->firstOrFail();

        // De naam staat er nog in; anders zegt het logboek alleen dát er
        // iets weg is en niet wát.
        $this->assertSame('Iets anders', $verwijderd->subject_label);
    }

    public function test_the_layout_screen_links_to_this_module(): void
    {
        // De diensten stonden op het indelingsscherm als "Nog niet te
        // beheren". Nu is er een scherm, dus hoort daar een link te staan.
        $plek = $this->plekOpHetScherm(PageSectionKey::Diensten);

        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    "sections.{$plek}.manageUrl",
                    route('website.diensten.index'),
                )
                ->etc());
    }

    /**
     * Op welke plek een onderdeel op het indelingsscherm staat.
     *
     * **Uitgerekend en niet opgeschreven.** Hier stond een vast getal, en
     * dat klopte tot er een module vóór de diensten bij kwam -- toen viel
     * deze test om op iets dat niets met zijn onderwerp te maken had. De
     * plek volgt uit `standaardPositie()`, dus die rekenen we hier uit.
     */
    private function plekOpHetScherm(PageSectionKey $sectie): int
    {
        $alles = PageSectionKey::cases();

        usort(
            $alles,
            fn (PageSectionKey $a, PageSectionKey $b) => $a->standaardPositie() <=> $b->standaardPositie(),
        );

        return (int) array_search($sectie, $alles, true);
    }
}
