<?php

namespace Tests\Feature\Website;

use App\Enums\ActivityAction;
use App\Enums\PageSectionKey;
use App\Models\ActivityEntry;
use App\Models\PageSection;
use App\Models\Service;
use App\Models\User;
use App\Support\Page\SectionContent;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het indelingsscherm: de volgorde van de website aanpassen.
 *
 * De zwaarste test hier is die met het verzoek dat de kop probeert te
 * verplaatsen. Het scherm laat de kop en de voettekst met een slotje zien,
 * maar een slotje in een sjabloon houdt niemand tegen die zelf een verzoek
 * opstelt. Wat er écht voor zorgt dat ze blijven staan, staat op de server,
 * en dat is wat hier wordt nagelopen.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class PageLayoutTest extends TestCase
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
     * De opslag verwacht álle verplaatsbare onderdelen, dus een test die
     * er één wil verzetten moet de rest ook meesturen.
     *
     * @param  array<int, PageSectionKey>  $volgorde
     * @param  array<string, bool>  $uit
     * @return array<int, array{key: string, visible: bool}>
     */
    private function payload(array $volgorde, array $uit = []): array
    {
        return array_map(fn (PageSectionKey $sectie) => [
            'key' => $sectie->value,
            'visible' => ! ($uit[$sectie->value] ?? false),
        ], $volgorde);
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('website.index'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('website.index'))->assertForbidden();
        $this->actingAs($user)
            ->put(route('website.update'), ['sections' => $this->payload(PageSectionKey::verplaatsbaar())])
            ->assertForbidden();
    }

    public function test_the_screen_lists_every_section_with_the_fixed_ones_at_the_ends(): void
    {
        $laatste = count(PageSectionKey::cases()) - 1;

        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Indeling')
                ->has('sections', count(PageSectionKey::cases()))
                ->where('sections.0.key', PageSectionKey::Hero->value)
                ->where('sections.0.fixed', true)
                // De laatste plek, uitgerekend en niet ingetypt: er komen
                // onderdelen bij, en dan hoort deze test niet om te vallen
                // op een getal.
                ->where('sections.'.$laatste.'.key', PageSectionKey::Footer->value)
                ->where('sections.'.$laatste.'.fixed', true)
                // De verplaatsbare onderdelen staan ertussen en zijn niet vast.
                ->where('sections.1.fixed', false));
    }

    public function test_the_address_bar_shows_the_website_and_not_the_portal(): void
    {
        /*
         * Het overzicht staat in een venster met een adresbalk. Die hoort
         * het adres van de wébsite te tonen; komt het portaal ooit op een
         * eigen subdomein, dan mag daar niet ineens dát adres staan.
         */
        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('host', parse_url(route('home'), PHP_URL_HOST)));
    }

    public function test_the_new_order_is_saved(): void
    {
        /*
         * De bestaande volgorde omgedraaid. Zo blijft de test kloppen als
         * er een onderdeel bij komt: hij gaat over "de volgorde die je
         * stuurt komt er zo in te staan" en niet over vier vaste namen.
         */
        $omgekeerd = array_reverse(PageSectionKey::verplaatsbaar());

        $this->actingAs($this->beheerder())
            ->put(route('website.update'), ['sections' => $this->payload($omgekeerd)])
            ->assertSessionHasNoErrors();

        $volgorde = PageSection::query()
            ->opVolgorde()
            ->get()
            ->map(fn (PageSection $rij) => $rij->key->value)
            ->all();

        // De kop en de voettekst staan er nog omheen: die houden 0 en 1000
        // en de rest wordt hernummerd als 1, 2, 3.
        $verwacht = [
            PageSectionKey::Hero->value,
            ...array_map(fn (PageSectionKey $sectie) => $sectie->value, $omgekeerd),
            PageSectionKey::Footer->value,
        ];

        $this->assertSame($verwacht, $volgorde);
    }

    public function test_a_section_can_be_switched_off(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.update'), [
                'sections' => $this->payload(
                    PageSectionKey::verplaatsbaar(),
                    [PageSectionKey::Werkwijze->value => true],
                ),
            ])
            ->assertSessionHasNoErrors();

        $this->assertFalse(
            PageSection::query()->where('key', PageSectionKey::Werkwijze)->value('visible'),
        );
    }

    /**
     * Het schuifje op het overzicht: één onderdeel, één verzoek.
     *
     * **Deze route is er gekomen na een melding van de eigenaar.** Het
     * schuifje stond op het overzicht te zien maar uitgeschakeld, omdat
     * alles via het bewerkvenster in één opslag zou gaan. Zijn reactie:
     * "het is wel raar dat dat bij allemaal zo is dat ik ze niet uit of
     * aan kan zetten". Elke andere lijst in het portaal werkt wél zo, dus
     * nu hier ook.
     */
    public function test_one_section_can_be_switched_off_from_the_overview(): void
    {
        $this->actingAs($this->beheerder())
            ->patch(
                route('website.zichtbaar', PageSectionKey::Werkwijze->value),
                ['visible' => false],
            )
            ->assertSessionHasNoErrors();

        $this->assertFalse(
            PageSection::query()->where('key', PageSectionKey::Werkwijze)->value('visible'),
        );
    }

    public function test_one_section_can_be_switched_back_on_from_the_overview(): void
    {
        PageSection::query()
            ->where('key', PageSectionKey::Werkwijze)
            ->update(['visible' => false]);

        $this->actingAs($this->beheerder())
            ->patch(
                route('website.zichtbaar', PageSectionKey::Werkwijze->value),
                ['visible' => true],
            )
            ->assertSessionHasNoErrors();

        $this->assertTrue(
            PageSection::query()->where('key', PageSectionKey::Werkwijze)->value('visible'),
        );
    }

    /**
     * En de volgorde blijft waar hij was.
     *
     * Dit is het verschil met `update()`, die alles hernummert. Zou dit
     * schuifje dat ook doen, dan verspringt de pagina van de eigenaar
     * omdat hij iets uitzette.
     */
    public function test_switching_one_section_leaves_the_order_alone(): void
    {
        $voor = PageSection::query()
            ->orderBy('key')
            ->pluck('position', 'key')
            ->all();

        $this->actingAs($this->beheerder())->patch(
            route('website.zichtbaar', PageSectionKey::Werkwijze->value),
            ['visible' => false],
        );

        $na = PageSection::query()
            ->orderBy('key')
            ->pluck('position', 'key')
            ->all();

        $this->assertSame($voor, $na);
    }

    /** Een vast onderdeel kan ook langs deze weg niet uit. */
    public function test_a_fixed_section_cannot_be_switched_off_one_by_one(): void
    {
        $this->actingAs($this->beheerder())->patch(
            route('website.zichtbaar', PageSectionKey::Footer->value),
            ['visible' => false],
        );

        $this->assertTrue(
            PageSection::query()->where('key', PageSectionKey::Footer)->value('visible'),
        );
    }

    public function test_an_unknown_section_cannot_be_switched(): void
    {
        $this->actingAs($this->beheerder())
            ->patch(route('website.zichtbaar', 'bestaat-niet'), ['visible' => false])
            ->assertRedirect();

        // Niets aangemaakt voor een sleutel die niet bestaat.
        $this->assertSame(
            count(PageSectionKey::cases()),
            PageSection::query()->count(),
        );
    }

    public function test_a_guest_cannot_switch_a_section(): void
    {
        $this->patch(
            route('website.zichtbaar', PageSectionKey::Werkwijze->value),
            ['visible' => false],
        )->assertRedirect(route('login'));

        $this->assertTrue(
            PageSection::query()->where('key', PageSectionKey::Werkwijze)->value('visible'),
        );
    }

    public function test_a_fixed_section_cannot_be_moved_by_a_crafted_request(): void
    {
        /*
         * Dit is het verzoek dat iemand zelf opstelt: de kop staat ertussen
         * alsof hij gewoon verplaatsbaar is. Het scherm zou hem nooit zo
         * versturen, en dat is precies waarom deze test bestaat.
         */
        $this->actingAs($this->beheerder())
            ->put(route('website.update'), [
                'sections' => $this->payload([
                    PageSectionKey::Diensten,
                    PageSectionKey::Hero,
                    PageSectionKey::Werkwijze,
                    PageSectionKey::Contact,
                ]),
            ])
            ->assertSessionHasErrors('sections');

        $this->assertSame(
            0,
            PageSection::query()->where('key', PageSectionKey::Hero)->value('position'),
        );
    }

    public function test_a_fixed_section_cannot_be_switched_off(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.update'), [
                'sections' => [
                    ...$this->payload(PageSectionKey::verplaatsbaar()),
                    ['key' => PageSectionKey::Footer->value, 'visible' => false],
                ],
            ])
            ->assertSessionHasErrors('sections');

        $this->assertTrue(
            PageSection::query()->where('key', PageSectionKey::Footer)->value('visible'),
        );
    }

    public function test_a_missing_section_is_refused(): void
    {
        /*
         * Streng in beide richtingen. Zou een half verzoek worden
         * geaccepteerd, dan blijft het weggelaten onderdeel op zijn oude
         * nummer staan en botst het met een ander -- en dan hangt de
         * volgorde op de website af van welke rij de database eerst geeft.
         */
        $this->actingAs($this->beheerder())
            ->put(route('website.update'), [
                'sections' => $this->payload([PageSectionKey::Diensten]),
            ])
            ->assertSessionHasErrors('sections');
    }

    public function test_an_unknown_section_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.update'), [
                'sections' => [['key' => 'prijzen', 'visible' => true]],
            ])
            ->assertSessionHasErrors('sections.0.key');
    }

    public function test_every_moved_section_gets_a_line_in_the_activity_log(): void
    {
        /*
         * De onderste naar boven. Daarmee verschuift álles: het verplaatste
         * onderdeel zelf en alle andere een plek naar beneden. Dat is ook
         * precies wat er in het logboek hoort te staan.
         */
        $verplaatsbaar = PageSectionKey::verplaatsbaar();
        $laatste = array_pop($verplaatsbaar);

        $this->actingAs($this->beheerder())
            ->put(route('website.update'), [
                'sections' => $this->payload([$laatste, ...$verplaatsbaar]),
            ]);

        $regels = ActivityEntry::query()
            ->where('subject_type', PageSection::class)
            ->ofAction(ActivityAction::Updated)
            ->get();

        // Elk verplaatsbaar onderdeel is verschoven, dus elk krijgt een
        // regel -- met de naam die de klant ook op het scherm ziet.
        $this->assertCount(count(PageSectionKey::verplaatsbaar()), $regels);
        $this->assertEqualsCanonicalizing(
            array_map(
                fn (PageSectionKey $sectie) => $sectie->label(),
                PageSectionKey::verplaatsbaar(),
            ),
            $regels->pluck('subject_label')->all(),
        );
    }

    public function test_saving_the_same_order_changes_nothing(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.update'), [
                'sections' => $this->payload(PageSectionKey::verplaatsbaar()),
            ])
            ->assertSessionHasNoErrors();

        // Geen enkele regel in het logboek: er is niets veranderd, en een
        // logboek dat volloopt met niet-wijzigingen is niet meer te lezen.
        $this->assertSame(
            0,
            ActivityEntry::query()->where('subject_type', PageSection::class)->count(),
        );
    }

    public function test_a_section_without_content_is_flagged_on_the_screen(): void
    {
        /*
         * Aan, maar leeg. Dit is de toestand waar het scherm een
         * uitroepteken bij zet: de eigenaar denkt dat het onderdeel op zijn
         * site staat en het staat er niet. Zie SectionContent.
         */
        app(SectionContent::class)->telt(PageSectionKey::Diensten, fn () => 0);

        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sections.1.key', PageSectionKey::Diensten->value)
                ->where('sections.1.visible', true)
                ->where('sections.1.filled', false)
                ->where('sections.1.count', 0)
                ->where('sections.1.live', false));
    }

    public function test_a_section_the_owner_switched_off_is_not_flagged_as_empty(): void
    {
        /*
         * Het verschil dat dit hele scherm rechtvaardigt: uitgezet is een
         * keuze en leeg is een probleem. Zouden ze er hetzelfde uitzien,
         * dan leert de eigenaar de waarschuwing te negeren.
         */
        // Wél inhoud, anders toetst deze test twee dingen tegelijk en
        // slaagt hij ook als "uitgezet" en "leeg" hetzelfde worden.
        Service::factory()->create();

        PageSection::query()
            ->where('key', PageSectionKey::Diensten)
            ->update(['visible' => false]);

        $this->actingAs($this->beheerder())
            ->get(route('website.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('sections.1.key', PageSectionKey::Diensten->value)
                ->where('sections.1.visible', false)
                ->where('sections.1.filled', true)
                ->where('sections.1.live', false));
    }
}
