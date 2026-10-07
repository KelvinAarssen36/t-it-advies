<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\AboutPoint;
use App\Models\AboutSetting;
use App\Models\ActivityEntry;
use App\Models\PageSection;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het onderdeel "Over mij": de teksten, de foto, de punten en de pagina.
 *
 * De vier dingen die hier vastliggen en die je makkelijk omgooit:
 *
 * 1. **De samenvatting is begrensd.** Zonder die grens wordt het korte blok
 *    het hele levensverhaal midden op de voorpagina, en is de aparte pagina
 *    er voor niets.
 * 2. **De aparte pagina bestaat alleen als hij aanstaat én er een verhaal
 *    in staat.** Twee voorwaarden, niet één: een pagina met alleen een kop,
 *    met een knop ernaartoe, is erger dan geen pagina.
 * 3. **Het medaillon is de standaard en een eigen foto de uitzondering.**
 *    Haalt de eigenaar zijn foto weg, dan staat het medaillon er weer -- hij
 *    kan niet zonder beeld komen te zitten.
 * 4. **De samenvatting valt níet terug op het Nederlands.** Staat er geen
 *    Engels, dan valt het blok weg op de Engelse site.
 *
 * Zie docs/architecture/modules/over-mij.md.
 */
class OverMijTest extends TestCase
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

    /** Een Engelse bezoeker, zoals `SetLocale` die ziet. */
    private function engels(): static
    {
        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9']);

        return $this;
    }

    /**
     * @param  array<string, mixed>  $anders
     * @return array<string, mixed>
     */
    private function invoer(array $anders = []): array
    {
        return [
            'summary_nl' => 'Ik werk sinds 2008 in de IT, en sinds 2016 voor mezelf.',
            'summary_en' => 'I have worked in IT since 2008, and for myself since 2016.',
            'page_enabled' => false,
            'page_title_nl' => 'Even voorstellen',
            'page_title_en' => 'Let me introduce myself',
            'page_intro_nl' => 'Hoe ik hier terecht ben gekomen.',
            'page_intro_en' => 'How I ended up here.',
            'story_nl' => "Eerste alinea.\n\nTweede alinea.",
            'story_en' => "First paragraph.\n\nSecond paragraph.",
            ...$anders,
        ];
    }

    /**
     * Alles bewaren, zoals de vensters het samen doen.
     *
     * **Een verzoek per venster en niet één voor alles, en dat is de kern
     * van de splitsing.** Het scherm heeft een venster voor het korte blok,
     * een voor de aparte pagina en een voor de foto, elk met zijn eigen
     * eindpunt. Zouden ze samen in één verzoek gaan, dan bewaart het ene
     * venster de velden van het andere als leeg -- en dat is precies wat
     * deze opzet onmogelijk maakt.
     *
     * @param  array<string, mixed>  $anders
     */
    private function bewaar(array $anders = []): TestResponse
    {
        $alles = $this->invoer($anders);

        $this->actingAs($this->beheerder())->put(
            route('website.over-mij.blok'),
            $this->alleen($alles, [
                'summary_nl', 'summary_en', 'machine_translated',
            ]),
        );

        $fotovelden = $this->alleen($alles, [
            'foto', 'foto_verwijderen', 'foto_zoom', 'foto_x', 'foto_y', 'foto_plaat',
        ]);

        if ($fotovelden !== []) {
            $this->actingAs($this->beheerder())->put(
                route('website.over-mij.foto'),
                $fotovelden,
            );
        }

        $this->actingAs($this->beheerder())->patch(
            route('website.over-mij.pagina-aan'),
            ['page_enabled' => $alles['page_enabled']],
        );

        return $this->actingAs($this->beheerder())->put(
            route('website.over-mij.pagina'),
            $this->alleen($alles, [
                'page_title_nl', 'page_title_en',
                'page_intro_nl', 'page_intro_en',
                'story_nl', 'story_en',
                'machine_translated',
            ]),
        );
    }

    /**
     * Alleen het korte blok bewaren.
     *
     * @param  array<string, mixed>  $anders
     */
    private function bewaarBlok(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())->put(
            route('website.over-mij.blok'),
            [
                'summary_nl' => 'Ik werk sinds 2008 in de IT, en sinds 2016 voor mezelf.',
                'summary_en' => 'I have worked in IT since 2008, and for myself since 2016.',
                ...$anders,
            ],
        );
    }

    /**
     * Alleen de foto bewaren.
     *
     * Een eigen eindpunt, want de foto staat op allebei de versies en hoort
     * dus bij geen van de twee.
     *
     * @param  array<string, mixed>  $anders
     */
    private function bewaarFoto(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())->put(
            route('website.over-mij.foto'),
            $anders,
        );
    }

    /**
     * Alleen de aparte pagina bewaren.
     *
     * @param  array<string, mixed>  $anders
     */
    private function bewaarPagina(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())->put(
            route('website.over-mij.pagina'),
            [
                'page_title_nl' => 'Even voorstellen',
                'page_intro_nl' => 'Hoe ik hier terecht ben gekomen.',
                'story_nl' => "Eerste alinea.\n\nTweede alinea.",
                ...$anders,
            ],
        );
    }

    /**
     * De gevraagde sleutels uit een rij halen.
     *
     * @param  array<string, mixed>  $rij
     * @param  array<int, string>  $sleutels
     * @return array<string, mixed>
     */
    private function alleen(array $rij, array $sleutels): array
    {
        return array_intersect_key($rij, array_flip($sleutels));
    }

    /* --- Rechten ----------------------------------------------------------- */

    public function test_every_route_needs_the_permission(): void
    {
        $punt = AboutPoint::factory()->create();
        $gebruiker = User::factory()->create();

        $this->actingAs($gebruiker)->get(route('website.over-mij.index'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.over-mij.blok'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.over-mij.pagina'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.over-mij.foto'))->assertForbidden();
        $this->actingAs($gebruiker)->patch(route('website.over-mij.pagina-aan'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.over-mij.kop'))->assertForbidden();
        $this->actingAs($gebruiker)->post(route('website.over-mij.punten.store'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.over-mij.punten.volgorde'))->assertForbidden();
        $this->actingAs($gebruiker)->put(route('website.over-mij.punten.update', $punt))->assertForbidden();
        $this->actingAs($gebruiker)->delete(route('website.over-mij.punten.destroy', $punt))->assertForbidden();

        $this->assertSame(1, AboutPoint::query()->count());
    }

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('website.over-mij.index'))->assertRedirect(route('login'));
    }

    /* --- Het scherm -------------------------------------------------------- */

    public function test_the_screen_sends_the_limits_from_one_place(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('website.over-mij.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/OverMij')
                ->where('opties.samenvattingMax', AboutSetting::SAMENVATTING_MAX)
                ->where('opties.verhaalMax', AboutSetting::VERHAAL_MAX)
                ->where('opties.puntenMax', AboutSetting::PUNTEN_MAX)
                ->where('leeg', true)
                ->etc());
    }

    /* --- De teksten -------------------------------------------------------- */

    public function test_the_texts_are_saved_in_both_languages(): void
    {
        $this->bewaar()->assertSessionHasNoErrors();

        $instelling = AboutSetting::query()->sole();

        $this->assertStringStartsWith('Ik werk sinds 2008', (string) $instelling->summary_nl);
        $this->assertStringStartsWith('I have worked', (string) $instelling->summary_en);
        $this->assertSame('Even voorstellen', $instelling->page_title_nl);
        $this->assertSame('Hoe ik hier terecht ben gekomen.', $instelling->page_intro_nl);
    }

    /** De witregels in het verhaal blijven staan; dat zijn de alinea's. */
    public function test_blank_lines_inside_the_story_survive(): void
    {
        $this->bewaarPagina(['story_nl' => "  Eerste.\n\nTweede.  "]);

        $this->assertSame(
            "Eerste.\n\nTweede.",
            AboutSetting::query()->sole()->story_nl,
        );
    }

    /**
     * Een te lange samenvatting wordt geweigerd, met uitleg.
     *
     * **De grens is de hele reden dat er twee velden zijn.** De
     * standaardmelding zegt alleen "mag niet meer dan 400 tekens bevatten";
     * die van ons zegt waar het dan wél hoort.
     */
    public function test_a_summary_that_is_too_long_is_refused(): void
    {
        $this->bewaarBlok([
            'summary_nl' => str_repeat('a', AboutSetting::SAMENVATTING_MAX + 1),
        ])->assertSessionHasErrors('summary_nl');

        $this->assertSame(0, AboutSetting::query()->count());
    }

    public function test_a_summary_exactly_on_the_limit_is_allowed(): void
    {
        $this->bewaarBlok([
            'summary_nl' => str_repeat('a', AboutSetting::SAMENVATTING_MAX),
        ])->assertSessionHasNoErrors();

        $this->assertSame(1, AboutSetting::query()->count());
    }

    /** Een leeg "Over mij" is een geldige toestand en geen fout. */
    public function test_an_empty_about_is_allowed(): void
    {
        $this->bewaarBlok(['summary_nl' => '', 'summary_en' => ''])
            ->assertSessionHasNoErrors();

        $this->bewaarPagina(['story_nl' => ''])->assertSessionHasNoErrors();

        $instelling = AboutSetting::query()->sole();

        $this->assertNull($instelling->summary_nl);
        $this->assertNull($instelling->story_nl);
    }

    /**
     * Het ene venster bewaren laat het andere met rust.
     *
     * **Dit is de hele reden dat er twee eindpunten zijn.** Het scherm is
     * een overzicht met twee bewerkvensters, en die sturen elk hun eigen
     * velden. Zat alles in één verzoek -- zoals eerst -- dan gaan de velden
     * die het venster niet kent als leeg mee, en wordt de andere helft
     * gewist. Dat is een fout met een stille uitkomst: je merkt het pas als
     * je verhaal weg is.
     */
    public function test_saving_one_half_leaves_the_other_alone(): void
    {
        $this->bewaar();

        // Alleen het korte blok opnieuw bewaren.
        $this->bewaarBlok(['summary_nl' => 'Een nieuwe samenvatting.']);

        $instelling = AboutSetting::query()->sole();

        $this->assertSame('Een nieuwe samenvatting.', $instelling->summary_nl);
        $this->assertSame("Eerste alinea.\n\nTweede alinea.", $instelling->story_nl);
        $this->assertSame('Even voorstellen', $instelling->page_title_nl);

        // En nu alleen de pagina.
        $this->bewaarPagina(['story_nl' => 'Een nieuw verhaal.']);

        $instelling = AboutSetting::query()->sole();

        $this->assertSame('Een nieuw verhaal.', $instelling->story_nl);
        $this->assertSame('Een nieuwe samenvatting.', $instelling->summary_nl);
    }

    /**
     * En het schuifje raakt geen tekst aan.
     *
     * Eén waarde omzetten hoort niet het hele formulier langs de validatie
     * te sturen -- zelfde afspraak als het online-schuifje bij de andere
     * modules.
     */
    public function test_the_switch_touches_no_text(): void
    {
        $this->bewaar();

        $this->actingAs($this->beheerder())
            ->patch(route('website.over-mij.pagina-aan'), ['page_enabled' => true])
            ->assertSessionHasNoErrors();

        $instelling = AboutSetting::query()->sole();

        $this->assertTrue($instelling->page_enabled);
        $this->assertSame("Eerste alinea.\n\nTweede alinea.", $instelling->story_nl);
        $this->assertStringStartsWith('Ik werk sinds 2008', (string) $instelling->summary_nl);
    }

    /* --- Het blok op de voorpagina ----------------------------------------- */

    public function test_the_block_reaches_the_front_page(): void
    {
        $this->bewaar();

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where(
                'about.samenvatting',
                'Ik werk sinds 2008 in de IT, en sinds 2016 voor mezelf.',
            )
            ->has('aboutHeading')
            ->etc());
    }

    /**
     * Zonder samenvatting verdwijnt het hele blok.
     *
     * Dat regelt de teller in AppServiceProvider: een kop met niets eronder
     * is slordiger dan geen kop.
     */
    public function test_without_a_summary_the_section_disappears(): void
    {
        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where(
                'sections',
                fn ($secties) => ! $secties->contains(PageSectionKey::OverMij->value),
            )
            ->where('about', null)
            ->where('aboutHeading', null)
            ->etc());
    }

    /**
     * En op de Engelse site verdwijnt hij zonder Engelse samenvatting.
     *
     * **Geen terugval, en dat is met opzet.** Een Engelse bezoeker die een
     * Nederlandse alinea over de eigenaar krijgt, krijgt iets wat hij niet
     * kan lezen op de plek waar hij vertrouwen moet opbouwen. De teller
     * rekent daarom met de taal mee.
     */
    public function test_an_english_visitor_without_english_sees_no_block(): void
    {
        $this->bewaar(['summary_en' => '']);

        $this->engels()->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    'sections',
                    fn ($secties) => ! $secties->contains(PageSectionKey::OverMij->value),
                )
                ->where('about', null)
                ->etc());
    }

    public function test_an_english_visitor_gets_the_english_summary(): void
    {
        $this->bewaar();

        $this->engels()->get(route('home'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where(
                    'about.samenvatting',
                    'I have worked in IT since 2008, and for myself since 2016.',
                )
                ->etc());
    }

    /* --- De aparte pagina -------------------------------------------------- */

    /** Uit betekent geen pagina en geen knop. */
    public function test_the_page_does_not_exist_when_it_is_off(): void
    {
        $this->bewaar(['page_enabled' => false]);

        $this->get(route('over-mij'))->assertNotFound();

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('about.pagina', false)
            ->etc());
    }

    public function test_the_page_works_when_it_is_on(): void
    {
        $this->bewaar(['page_enabled' => true]);

        $this->get(route('over-mij'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('public/OverMij')
                ->where('titel', 'Even voorstellen')
                ->where('inleiding', 'Hoe ik hier terecht ben gekomen.')
                ->where('verhaal', "Eerste alinea.\n\nTweede alinea.")
                ->etc());

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('about.pagina', true)
            ->etc());
    }

    /**
     * Aan zonder verhaal is géén pagina.
     *
     * **Dit is de tweede voorwaarde, en hij is het belangrijkst.** Zet de
     * eigenaar het schuifje om maar vult hij niets in, dan zou er een pagina
     * met alleen een kop komen te staan -- met een knop op zijn voorpagina
     * die daarheen wijst.
     */
    public function test_on_without_a_story_is_still_no_page(): void
    {
        $this->bewaar(['page_enabled' => true, 'story_nl' => '', 'story_en' => '']);

        $this->get(route('over-mij'))->assertNotFound();

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('about.pagina', false)
            ->etc());
    }

    /** Staat het onderdeel uit, dan bestaat de pagina ook niet. */
    public function test_a_switched_off_section_has_no_page_either(): void
    {
        $this->bewaar(['page_enabled' => true]);

        PageSection::query()
            ->where('key', PageSectionKey::OverMij)
            ->update(['visible' => false]);

        $this->get(route('over-mij'))->assertNotFound();
    }

    /** Zonder eigen titel valt de pagina terug op de kop van het blok. */
    public function test_without_its_own_title_the_page_falls_back(): void
    {
        $this->bewaar(['page_enabled' => true, 'page_title_nl' => '']);

        $this->get(route('over-mij'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('titel', 'Wie het werk doet')
                ->etc());
    }

    /* --- De punten --------------------------------------------------------- */

    public function test_a_point_can_be_added_changed_and_removed(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.over-mij.punten.store'), [
                'text_nl' => 'Geen vendor lock-in',
                'text_en' => 'No vendor lock-in',
            ])
            ->assertSessionHasNoErrors();

        $punt = AboutPoint::query()->sole();

        $this->assertSame('Geen vendor lock-in', $punt->text_nl);
        $this->assertSame(1, (int) $punt->position);

        $this->actingAs($this->beheerder())
            ->put(route('website.over-mij.punten.update', $punt), [
                'text_nl' => 'Werkt met wat er al staat',
            ]);

        $this->assertSame('Werkt met wat er al staat', $punt->fresh()->text_nl);

        $this->actingAs($this->beheerder())
            ->delete(route('website.over-mij.punten.destroy', $punt));

        $this->assertSame(0, AboutPoint::query()->count());
    }

    /**
     * Boven het maximum komt er geen punt meer bij.
     *
     * De grens gaat over hoeveel er al zijn en niet over wat er binnenkomt,
     * dus hij staat in de controller en niet in de validatieregels.
     */
    public function test_the_maximum_number_of_points_is_enforced(): void
    {
        AboutPoint::factory()->count(AboutSetting::PUNTEN_MAX)->create();

        $this->actingAs($this->beheerder())
            ->post(route('website.over-mij.punten.store'), ['text_nl' => 'Nog een']);

        $this->assertSame(
            AboutSetting::PUNTEN_MAX,
            AboutPoint::query()->count(),
        );
    }

    public function test_the_order_of_the_points_can_be_changed(): void
    {
        $een = AboutPoint::factory()->create(['position' => 1]);
        $twee = AboutPoint::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())
            ->put(route('website.over-mij.punten.volgorde'), [
                'punten' => [['id' => $twee->id], ['id' => $een->id]],
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, (int) $twee->fresh()->position);
        $this->assertSame(2, (int) $een->fresh()->position);
    }

    /** Een halve lijst wordt geweigerd; anders botsen de posities. */
    public function test_an_incomplete_order_is_refused(): void
    {
        $een = AboutPoint::factory()->create(['position' => 1]);
        AboutPoint::factory()->create(['position' => 2]);

        $this->actingAs($this->beheerder())
            ->put(route('website.over-mij.punten.volgorde'), [
                'punten' => [['id' => $een->id]],
            ]);

        $this->assertSame(1, (int) $een->fresh()->position);
    }

    /**
     * Een punt zonder Engels valt weg op de Engelse pagina.
     *
     * **Geen terugval, anders dan bij een vraag in de FAQ.** Een rijtje met
     * drie Engelse en twee Nederlandse punten is slordiger dan een rijtje
     * van drie; daar is het de helft van het enige dat er staat.
     */
    public function test_a_point_without_english_drops_off_the_english_page(): void
    {
        $this->bewaar(['page_enabled' => true]);

        AboutPoint::factory()->create([
            'text_nl' => 'Alleen Nederlands',
            'text_en' => null,
            'position' => 1,
        ]);

        AboutPoint::factory()->create([
            'text_nl' => 'Met Engels',
            'text_en' => 'With English',
            'position' => 2,
        ]);

        $this->get(route('over-mij'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('punten', 2)
                ->etc());

        $this->engels()->get(route('over-mij'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('punten', 1)
                ->where('punten.0', 'With English')
                ->etc());
    }

    /* --- De foto ----------------------------------------------------------- */

    /** Zonder eigen foto is het medaillon het beeld, met al zijn maten. */
    public function test_the_medallion_is_the_default(): void
    {
        $this->bewaar();

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('about.foto.eigen', false)
            ->where('about.foto.src', '/images/persoon-medaillon-640.webp')
            ->whereNot('about.foto.srcset', null)
            ->etc());
    }

    public function test_an_own_photo_takes_precedence(): void
    {
        Storage::fake(AboutSetting::SCHIJF);

        $this->bewaarFoto([
            'foto' => UploadedFile::fake()->image('ik.jpg', 800, 800),
        ])->assertSessionHasNoErrors();

        $instelling = AboutSetting::query()->sole();

        $this->assertNotNull($instelling->photo_path);
        Storage::disk(AboutSetting::SCHIJF)->assertExists((string) $instelling->photo_path);

        /*
         * En er moet een samenvatting staan, anders is er geen blok om de
         * foto op te zetten. Dat is sinds de foto een eigen eindpunt heeft
         * een losse handeling: alleen een foto bewaren zet het onderdeel
         * niet op de site.
         */
        $this->bewaarBlok();

        $this->get(route('home'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('about.foto.eigen', true)
            ->where('about.foto.srcset', null)
            ->etc());
    }

    /**
     * Een foto die blijft staan bij een gewone opslag.
     *
     * Zou het veld er altijd in zitten, dan raakt de eigenaar zijn foto
     * kwijt zodra hij een woord in zijn verhaal verbetert.
     */
    public function test_a_save_without_a_file_keeps_the_photo(): void
    {
        Storage::fake(AboutSetting::SCHIJF);

        $this->bewaarFoto(['foto' => UploadedFile::fake()->image('ik.jpg', 800, 800)]);

        $pad = AboutSetting::query()->sole()->photo_path;

        $this->bewaarBlok(['summary_nl' => 'Een verbeterde samenvatting.']);

        $this->assertSame($pad, AboutSetting::query()->sole()->photo_path);
    }

    /** En weghalen brengt het medaillon terug. */
    public function test_removing_the_photo_brings_back_the_medallion(): void
    {
        Storage::fake(AboutSetting::SCHIJF);

        $this->bewaarFoto(['foto' => UploadedFile::fake()->image('ik.jpg', 800, 800)]);

        $pad = (string) AboutSetting::query()->sole()->photo_path;

        $this->bewaarFoto(['foto_verwijderen' => true]);

        $instelling = AboutSetting::query()->sole();

        $this->assertNull($instelling->photo_path);
        $this->assertFalse($instelling->foto()['eigen']);
        Storage::disk(AboutSetting::SCHIJF)->assertMissing($pad);
    }

    /** Een te kleine foto wordt geweigerd; een portret van 48 is een vlek. */
    public function test_a_photo_that_is_too_small_is_refused(): void
    {
        Storage::fake(AboutSetting::SCHIJF);

        $this->bewaarFoto([
            'foto' => UploadedFile::fake()->image('klein.jpg', 100, 100),
        ])->assertSessionHasErrors('foto');
    }

    /**
     * Alleen het foto-eindpunt raakt de foto aan.
     *
     * De foto staat op allebei de versies en heeft daarom een eigen venster
     * met een eigen eindpunt. Zouden de twee tekstvensters een bestand wél
     * aannemen, dan zijn er drie plekken die dezelfde kolom schrijven en
     * wint degene die het laatst opslaat -- terwijl het scherm zegt dat de
     * foto apart staat. Beide kanten worden hier getest, want een regel die
     * er bij één van de twee insluipt valt anders niet op.
     */
    public function test_only_the_photo_endpoint_touches_the_photo(): void
    {
        Storage::fake(AboutSetting::SCHIJF);

        $this->bewaarFoto(['foto' => UploadedFile::fake()->image('ik.jpg', 800, 800)]);

        $pad = (string) AboutSetting::query()->sole()->photo_path;

        $erbij = [
            'foto' => UploadedFile::fake()->image('anders.jpg', 800, 800),
            'foto_verwijderen' => true,
        ];

        $this->bewaarPagina($erbij)->assertSessionHasNoErrors();
        $this->bewaarBlok($erbij)->assertSessionHasNoErrors();

        $this->assertSame($pad, AboutSetting::query()->sole()->photo_path);
        Storage::disk(AboutSetting::SCHIJF)->assertExists($pad);
    }

    /* --- Het activiteitenlogboek ------------------------------------------- */

    public function test_the_activity_log_records_the_changes(): void
    {
        $this->bewaar();

        $this->actingAs($this->beheerder())
            ->post(route('website.over-mij.punten.store'), ['text_nl' => 'Een punt']);

        $this->assertGreaterThan(
            0,
            ActivityEntry::query()->where('subject_type', AboutSetting::class)->count(),
        );

        $this->assertGreaterThan(
            0,
            ActivityEntry::query()->where('subject_type', AboutPoint::class)->count(),
        );
    }
}
