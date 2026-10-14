<?php

namespace Tests\Feature\Website;

use App\Enums\PageSectionKey;
use App\Models\ActivityEntry;
use App\Models\LinkedinSetting;
use App\Models\SectionHeading;
use App\Models\User;
use App\Support\Translation\Vertaler;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Support\SessionKey;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Het LinkedIn-blok: de teksten die de eigenaar zelf beheert.
 *
 * Volgt de tabel uit docs/development/testen.md. Twee dingen zijn hier
 * bijzonder:
 *
 * - **De link is geen veld.** Hij ligt vast in config/site.php; een `url`
 *   die toch wordt meegestuurd verandert niets, en het scherm toont hem
 *   alleen.
 * - **Leeg is de standaard.** Een lege knoptekst is "Bekijk [naam]" in de
 *   taal van de bezoeker, en de kop valt zonder rij terug op de tekst die
 *   er altijd stond. Een bestaande site ziet na de update niets veranderen.
 *
 * Zie docs/architecture/modules/linkedin.md.
 */
class LinkedinCrudTest extends TestCase
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
     * @return array<string, string>
     */
    private function kop(array $anders = []): array
    {
        return [
            'eyebrow_nl' => 'Volgen',
            'eyebrow_en' => 'Follow',
            'title_nl' => 'Lees mee op LinkedIn',
            'title_en' => 'Read along on LinkedIn',
            'intro_nl' => 'Korte stukken over beheer en migraties.',
            'intro_en' => 'Short pieces on management and migrations.',
            ...$anders,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function blok(string $taal = 'nl'): ?array
    {
        $props = $this->withSession(['locale' => $taal])
            ->get(route('home'))
            ->viewData('page')['props'];

        return $props['linkedinBlock'];
    }

    /* --- Rechten ------------------------------------------------------- */

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('website.linkedin.index'))
            ->assertRedirect(route('login'));

        $this->put(route('website.linkedin.kop'), $this->kop())
            ->assertRedirect(route('login'));

        $this->put(route('website.linkedin.knop'), ['button_nl' => 'Volg mij'])
            ->assertRedirect(route('login'));

        $this->assertDatabaseCount('linkedin_settings', 0);
        $this->assertDatabaseMissing('section_headings', ['section' => 'linkedin']);
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $zonder = User::factory()->create();

        $this->actingAs($zonder)
            ->get(route('website.linkedin.index'))
            ->assertForbidden();

        $this->actingAs($zonder)
            ->put(route('website.linkedin.kop'), $this->kop())
            ->assertForbidden();

        $this->actingAs($zonder)
            ->put(route('website.linkedin.knop'), ['button_nl' => 'Volg mij'])
            ->assertForbidden();

        $this->assertDatabaseCount('linkedin_settings', 0);
        $this->assertDatabaseMissing('section_headings', ['section' => 'linkedin']);
    }

    /* --- Het scherm ----------------------------------------------------- */

    /**
     * Op een verse installatie staat de tekst die er altijd stond.
     *
     * Er is geen rij voor de kop en geen voor de knop; het scherm hoort dan
     * de standaard te tonen en niet leeg te zijn, en een GET schrijft niets
     * weg.
     */
    public function test_the_screen_shows_the_defaults_without_any_rows(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('website.linkedin.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Linkedin')
                ->where('kop.eyebrow_nl', 'LinkedIn')
                ->where('kop.title_nl', 'Blijf op de hoogte')
                ->where('kop.title_en', 'Stay in the loop')
                ->where('instelling.button_nl', null)
                ->where('instelling.button_en', null)
                ->where('instelling.automatisch_vertaald', false)
                ->where('adres', (string) config('site.linkedin'))
                ->where('standaardKnop.nl', 'Bekijk '.config('security.portal_account.name'))
                ->where('standaardKnop.en', 'View '.config('security.portal_account.name'))
                ->where('opties.knopMax', LinkedinSetting::KNOP_MAX)
                ->has('kanVertalen'));

        $this->assertDatabaseCount('linkedin_settings', 0);
        $this->assertDatabaseMissing('section_headings', ['section' => 'linkedin']);
    }

    /* --- Opslaan -------------------------------------------------------- */

    public function test_the_heading_is_saved_in_both_languages(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.linkedin.kop'), $this->kop())
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $kop = SectionHeading::voor(PageSectionKey::Linkedin);

        $this->assertTrue($kop->exists);
        $this->assertSame('Lees mee op LinkedIn', $kop->title_nl);
        $this->assertSame('Read along on LinkedIn', $kop->title_en);
        $this->assertSame('Follow', $kop->eyebrow_en);
        $this->assertSame(
            'De tekst van je LinkedIn-blok is aangepast.',
            session('inertia.flash_data.toast.message'),
        );
    }

    public function test_the_button_text_is_saved_in_both_languages(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.linkedin.knop'), [
                'button_nl' => 'Volg mij op LinkedIn',
                'button_en' => 'Follow me on LinkedIn',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('linkedin_settings', [
            'button_nl' => 'Volg mij op LinkedIn',
            'button_en' => 'Follow me on LinkedIn',
        ]);
        $this->assertSame(
            'De tekst op je LinkedIn-knop is aangepast.',
            session('inertia.flash_data.toast.message'),
        );
    }

    /**
     * Het ene venster wist de velden van het andere niet.
     *
     * Dezelfde les als bij de voettekst en "Over mij": twee vensters,
     * twee eindpunten, en elk raakt alleen zijn eigen velden aan.
     */
    public function test_one_dialog_does_not_wipe_the_other(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)
            ->put(route('website.linkedin.knop'), ['button_nl' => 'Volg mij']);

        $this->actingAs($beheerder)
            ->put(route('website.linkedin.kop'), $this->kop());

        $this->assertSame('Volg mij', LinkedinSetting::huidige()->button_nl);

        $this->actingAs($beheerder)
            ->put(route('website.linkedin.knop'), ['button_nl' => 'Volg mij nu']);

        $this->assertSame(
            'Lees mee op LinkedIn',
            SectionHeading::voor(PageSectionKey::Linkedin)->title_nl,
        );
    }

    public function test_there_is_never_more_than_one_button_row(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)
            ->put(route('website.linkedin.knop'), ['button_nl' => 'Eerst']);

        $this->actingAs($beheerder)
            ->put(route('website.linkedin.knop'), ['button_nl' => 'Daarna']);

        $this->assertDatabaseCount('linkedin_settings', 1);
        $this->assertSame('Daarna', LinkedinSetting::huidige()->button_nl);
    }

    /** Een lege knop wordt `null`, en dan geldt weer de standaardtekst. */
    public function test_an_emptied_button_text_becomes_null(): void
    {
        LinkedinSetting::query()->create(['button_nl' => 'Volg mij', 'button_en' => 'Follow me']);

        $this->actingAs($this->beheerder())
            ->put(route('website.linkedin.knop'), ['button_nl' => '   ', 'button_en' => '']);

        $instelling = LinkedinSetting::huidige();

        $this->assertNull($instelling->button_nl);
        $this->assertNull($instelling->button_en);
        $this->assertSame('Bekijk '.config('security.portal_account.name'), $this->blok()['knop']);
    }

    /** Een lege eerste opslag maakt geen rij en meldt geen wijziging. */
    public function test_saving_an_empty_button_on_a_fresh_install_changes_nothing(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.linkedin.knop'), ['button_nl' => '', 'button_en' => ''])
            ->assertRedirect();

        $this->assertSame(0, LinkedinSetting::query()->count());
        $this->assertSame('Er was niets veranderd.', session('inertia.flash_data.toast.message'));
    }

    public function test_saving_the_same_heading_twice_reports_no_change(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)->put(route('website.linkedin.kop'), $this->kop());
        $this->actingAs($beheerder)->put(route('website.linkedin.kop'), $this->kop());

        $this->assertSame('Er was niets veranderd.', session('inertia.flash_data.toast.message'));
    }

    /* --- De link ligt vast ---------------------------------------------- */

    /**
     * Een meegestuurd adres verandert niets.
     *
     * Er is geen veld voor, maar een aangepast verzoek kan het toch
     * proberen. Het mag nergens terechtkomen: niet in de database, niet op
     * de site.
     */
    public function test_a_sent_url_is_ignored(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.linkedin.knop'), [
                'button_nl' => 'Volg mij',
                'url' => 'https://example.org/elders',
                'adres' => 'https://example.org/elders',
            ])
            ->assertSessionHasNoErrors();

        $props = $this->get(route('home'))->viewData('page')['props'];

        $this->assertSame(config('site.linkedin'), $props['linkedin']);
        $this->assertStringNotContainsString(
            'example.org',
            (string) json_encode(LinkedinSetting::huidige()->toArray()),
        );
    }

    /* --- Validatie ------------------------------------------------------ */

    public function test_a_button_text_that_is_too_long_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.linkedin.knop'), [
                'button_nl' => str_repeat('a', LinkedinSetting::KNOP_MAX + 1),
            ])
            ->assertSessionHasErrors('button_nl');

        $this->actingAs($this->beheerder())
            ->put(route('website.linkedin.knop'), [
                'button_en' => str_repeat('a', LinkedinSetting::KNOP_MAX + 1),
            ])
            ->assertSessionHasErrors('button_en');

        $this->assertDatabaseCount('linkedin_settings', 0);
    }

    public function test_a_heading_without_a_title_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.linkedin.kop'), $this->kop(['title_nl' => '']))
            ->assertSessionHasErrors('title_nl');

        $this->assertDatabaseMissing('section_headings', ['section' => 'linkedin']);
    }

    public function test_a_heading_that_is_too_long_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.linkedin.kop'), $this->kop([
                'title_nl' => str_repeat('a', 121),
                'intro_en' => str_repeat('a', 301),
            ]))
            ->assertSessionHasErrors(['title_nl', 'intro_en']);
    }

    /* --- Vertalen ------------------------------------------------------- */

    /**
     * Het merkje "automatisch vertaald" komt mee en gaat weer weg.
     *
     * Het zegt dat het Engels dat er nú staat van de dienst komt. Typt de
     * eigenaar daarna zelf, dan stuurt het scherm `false` mee.
     */
    public function test_the_translation_mark_follows_the_button_text(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)->put(route('website.linkedin.knop'), [
            'button_nl' => 'Volg mij',
            'button_en' => 'Follow me',
            'machine_translated' => true,
        ]);

        $this->assertNotNull(LinkedinSetting::huidige()->button_translated_at);

        $this->actingAs($beheerder)->put(route('website.linkedin.knop'), [
            'button_nl' => 'Volg mij',
            'button_en' => 'Follow me, please',
            'machine_translated' => false,
        ]);

        $this->assertNull(LinkedinSetting::huidige()->button_translated_at);
    }

    /** De vertaalknop kent het veld van de knop, en dat van de kop. */
    public function test_the_translate_route_accepts_the_linkedin_fields(): void
    {
        $this->app->instance(Vertaler::class, new class implements Vertaler
        {
            public function beschikbaar(): bool
            {
                return true;
            }

            /**
             * @param  array<string, string|null>  $teksten
             * @return array<string, string>
             */
            public function naarEngels(array $teksten): array
            {
                $gevuld = array_filter($teksten, fn (?string $tekst) => filled($tekst));

                return array_map(fn (string $tekst) => 'EN: '.$tekst, $gevuld);
            }
        });

        $this->actingAs($this->beheerder())
            ->post(route('website.vertalen'), [
                'button_nl' => 'Volg mij',
                'title_nl' => 'Blijf op de hoogte',
            ])
            ->assertSessionHasNoErrors();

        /** @var array<string, string> $vertaling */
        $vertaling = session(SessionKey::FLASH_DATA)['vertaling'] ?? [];

        $this->assertSame('EN: Volg mij', $vertaling['button_en'] ?? null);
        $this->assertSame('EN: Blijf op de hoogte', $vertaling['title_en'] ?? null);

        // En er is niets opgeslagen: de knop vult alleen het formulier.
        $this->assertDatabaseCount('linkedin_settings', 0);
    }

    /* --- Wat de bezoeker ziet ------------------------------------------- */

    public function test_the_visitor_sees_the_old_text_by_default(): void
    {
        $naam = (string) config('security.portal_account.name');

        $nl = $this->blok('nl');
        $this->assertSame('LinkedIn', $nl['kop']['opschrift']);
        $this->assertSame('Blijf op de hoogte', $nl['kop']['titel']);
        $this->assertSame('Bekijk '.$naam, $nl['knop']);

        $en = $this->blok('en');
        $this->assertSame('Stay in the loop', $en['kop']['titel']);
        $this->assertSame('View '.$naam, $en['knop']);
    }

    public function test_the_visitor_sees_the_owners_text_in_each_language(): void
    {
        SectionHeading::query()->create([
            'section' => PageSectionKey::Linkedin,
            ...$this->kop(),
        ]);
        LinkedinSetting::query()->create([
            'button_nl' => 'Volg mij',
            'button_en' => 'Follow me',
        ]);

        $nl = $this->blok('nl');
        $this->assertSame('Lees mee op LinkedIn', $nl['kop']['titel']);
        $this->assertSame('Volg mij', $nl['knop']);

        $en = $this->blok('en');
        $this->assertSame('Read along on LinkedIn', $en['kop']['titel']);
        $this->assertSame('Follow', $en['kop']['opschrift']);
        $this->assertSame('Follow me', $en['knop']);
    }

    /**
     * De terugvalregels per veld.
     *
     * Titel en opschrift vallen terug op het Nederlands, zoals bij elke kop;
     * de zin valt weg; en de knop valt terug op de Engelse standaardtekst,
     * niet op het Nederlands -- een Nederlands woord op een Engelse knop
     * leest als een fout.
     */
    public function test_empty_english_falls_back_per_field(): void
    {
        SectionHeading::query()->create([
            'section' => PageSectionKey::Linkedin,
            ...$this->kop(['eyebrow_en' => null, 'title_en' => null, 'intro_en' => null]),
        ]);
        LinkedinSetting::query()->create(['button_nl' => 'Volg mij']);

        $en = $this->blok('en');

        $this->assertSame('Volgen', $en['kop']['opschrift']);
        $this->assertSame('Lees mee op LinkedIn', $en['kop']['titel']);
        $this->assertNull($en['kop']['inleiding']);
        $this->assertSame('View '.config('security.portal_account.name'), $en['knop']);
    }

    /** Uitgezet: dan gaat er ook niets mee naar de pagina. */
    public function test_a_switched_off_block_sends_nothing(): void
    {
        $this->actingAs($this->beheerder())
            ->patch(route('website.zichtbaar', PageSectionKey::Linkedin->value), ['visible' => false]);

        $this->assertNull($this->blok());
    }

    /* --- Logboek -------------------------------------------------------- */

    public function test_both_changes_land_in_the_activity_log(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)->put(route('website.linkedin.kop'), $this->kop());
        $this->actingAs($beheerder)->put(route('website.linkedin.knop'), ['button_nl' => 'Volg mij']);

        $this->assertTrue(
            ActivityEntry::query()->where('subject_type', SectionHeading::class)->exists(),
            'De kop staat niet in het activiteitenlogboek.',
        );
        $this->assertTrue(
            ActivityEntry::query()->where('subject_type', LinkedinSetting::class)->exists(),
            'De knoptekst staat niet in het activiteitenlogboek.',
        );
    }
}
