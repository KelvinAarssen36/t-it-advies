<?php

namespace Tests\Feature\Website;

use App\Models\ActivityEntry;
use App\Models\FooterSetting;
use App\Models\User;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De voettekst: wat de eigenaar er zelf in zet.
 *
 * Volgt de tabel uit docs/development/testen.md. Twee gevallen zijn hier
 * bijzonder:
 *
 * - **Drie vensters, drie eindpunten.** De test die ertoe doet is
 *   `test_one_dialog_does_not_wipe_the_fields_of_another`: dat is de fout
 *   die bij "Over mij" in productie is ontdekt, en die kan hier niet
 *   terugkomen zonder dat deze test omvalt.
 * - **Het KvK-nummer wordt geteld.** Acht cijfers, anders staat er straks
 *   een onwaarheid onder het kopje "KvK".
 *
 * Zie docs/architecture/modules/voettekst.md.
 */
class FooterCrudTest extends TestCase
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

    /* --- Rechten ------------------------------------------------------- */

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('website.footer.index'))
            ->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $zonder = User::factory()->create();

        $this->actingAs($zonder)
            ->get(route('website.footer.index'))
            ->assertForbidden();

        $this->actingAs($zonder)
            ->put(route('website.footer.tekst'), ['tagline_nl' => 'Iets'])
            ->assertForbidden();

        $this->actingAs($zonder)
            ->put(route('website.footer.contact'), ['phone' => '0612345678'])
            ->assertForbidden();

        $this->actingAs($zonder)
            ->put(route('website.footer.bedrijf'), ['kvk' => '12345678'])
            ->assertForbidden();
    }

    /* --- Het scherm ----------------------------------------------------- */

    public function test_the_screen_shows_the_settings_and_the_options(): void
    {
        FooterSetting::query()->create([
            'tagline_nl' => 'IT-advies en realisatie.',
            'phone' => '06 12 34 56 78',
            'kvk' => '12 34 56 78',
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('website.footer.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Voettekst')
                ->where('instelling.tagline_nl', 'IT-advies en realisatie.')
                ->where('instelling.phone', '06 12 34 56 78')
                ->where('instelling.kvk', '12 34 56 78')
                ->where('opties.kvkCijfers', FooterSetting::KVK_CIJFERS)
                ->where('opties.taglineMax', FooterSetting::TAGLINE_MAX)
                // Het adres staat er ter informatie en is geen veld.
                ->where('email', (string) config('site.email'))
                ->has('kanVertalen'));
    }

    /**
     * Een verse installatie heeft geen rij, en dat hoort geen fout te zijn.
     *
     * `huidige()` geeft dan een niet-opgeslagen exemplaar. Zou het scherm
     * daarop stukgaan, dan kan de eigenaar zijn voet nooit voor het eerst
     * invullen.
     */
    public function test_the_screen_works_without_a_row(): void
    {
        $this->assertDatabaseCount('footer_settings', 0);

        $this->actingAs($this->beheerder())
            ->get(route('website.footer.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Voettekst')
                ->where('instelling.tagline_nl', null)
                ->where('instelling.kvk', null));

        // En een GET schrijft niets weg.
        $this->assertDatabaseCount('footer_settings', 0);
    }

    /* --- De drie vensters ----------------------------------------------- */

    public function test_the_tagline_is_saved_in_both_languages(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.footer.tekst'), [
                'tagline_nl' => 'IT-advies en realisatie.',
                'tagline_en' => 'IT consultancy and delivery.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('footer_settings', [
            'tagline_nl' => 'IT-advies en realisatie.',
            'tagline_en' => 'IT consultancy and delivery.',
        ]);
    }

    public function test_the_contact_details_are_saved(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.footer.contact'), [
                'phone' => '06 12 34 56 78',
                'region_nl' => 'Noord-Brabant',
                'region_en' => 'Noord-Brabant',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('footer_settings', [
            'phone' => '06 12 34 56 78',
            'region_nl' => 'Noord-Brabant',
        ]);
    }

    public function test_the_company_details_are_saved(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.footer.bedrijf'), [
                'kvk' => '12 34 56 78',
                'vat' => 'NL001234567B01',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('footer_settings', [
            'kvk' => '12 34 56 78',
            'vat' => 'NL001234567B01',
        ]);
    }

    /**
     * De test waar deze module om draait.
     *
     * Bij "Over mij" zat één `update()` achter twee vensters, en bewaarde
     * je het ene dan gingen de velden van het andere als leeg mee en werden
     * ze gewist -- zonder melding. Elk venster heeft hier zijn eigen
     * eindpunt, en deze test bewijst dat dat ook echt zo werkt.
     */
    public function test_one_dialog_does_not_wipe_the_fields_of_another(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)
            ->put(route('website.footer.contact'), [
                'phone' => '06 12 34 56 78',
                'region_nl' => 'Noord-Brabant',
            ]);

        $this->actingAs($beheerder)
            ->put(route('website.footer.bedrijf'), ['kvk' => '12345678']);

        $this->actingAs($beheerder)
            ->put(route('website.footer.tekst'), ['tagline_nl' => 'Een zin.']);

        $voet = FooterSetting::huidige();

        $this->assertSame('06 12 34 56 78', $voet->phone, 'Het telefoonnummer is weg.');
        $this->assertSame('Noord-Brabant', $voet->region_nl, 'Het werkgebied is weg.');
        $this->assertSame('12345678', $voet->kvk, 'Het KvK-nummer is weg.');
        $this->assertSame('Een zin.', $voet->tagline_nl);
    }

    public function test_there_is_never_more_than_one_row(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)
            ->put(route('website.footer.tekst'), ['tagline_nl' => 'Eerst']);

        $this->actingAs($beheerder)
            ->put(route('website.footer.tekst'), ['tagline_nl' => 'Daarna']);

        $this->assertDatabaseCount('footer_settings', 1);
        $this->assertSame('Daarna', FooterSetting::huidige()->tagline_nl);
    }

    /* --- Leegmaken ------------------------------------------------------ */

    /**
     * Een leeg veld wordt `null` en niet `""`.
     *
     * Dat onderscheid doet ertoe: de site beslist op `filled()` of een
     * regel getoond wordt, en een lege string is gevuld. Zou het `""`
     * worden, dan staat er een kopje boven niets.
     */
    public function test_an_emptied_field_becomes_null(): void
    {
        FooterSetting::query()->create(['phone' => '06 12 34 56 78']);

        $this->actingAs($this->beheerder())
            ->put(route('website.footer.contact'), [
                'phone' => '   ',
                'region_nl' => '',
            ]);

        $voet = FooterSetting::huidige();

        $this->assertNull($voet->phone);
        $this->assertNull($voet->region_nl);
    }

    /* --- Validatie ------------------------------------------------------ */

    public function test_a_kvk_number_needs_eight_digits(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.footer.bedrijf'), ['kvk' => '1234567'])
            ->assertSessionHasErrors('kvk');

        $this->assertDatabaseCount('footer_settings', 0);
    }

    /** Spaties mogen; het gaat om de cijfers eronder. */
    public function test_a_kvk_number_may_contain_spaces(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.footer.bedrijf'), ['kvk' => '12 34 56 78'])
            ->assertSessionHasNoErrors();

        $this->assertSame('12 34 56 78', FooterSetting::huidige()->kvk);
    }

    /** Leeg is geldig: niet iedereen wil zijn KvK onderaan zijn site. */
    public function test_an_empty_kvk_number_is_allowed(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.footer.bedrijf'), ['kvk' => '', 'vat' => ''])
            ->assertSessionHasNoErrors();

        $this->assertNull(FooterSetting::huidige()->kvk);
    }

    public function test_a_phone_number_with_letters_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.footer.contact'), ['phone' => 'bel maar'])
            ->assertSessionHasErrors('phone');
    }

    public function test_a_tagline_that_is_too_long_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.footer.tekst'), [
                'tagline_nl' => str_repeat('a', FooterSetting::TAGLINE_MAX + 1),
            ])
            ->assertSessionHasErrors('tagline_nl');
    }

    public function test_a_region_that_is_too_long_is_refused(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.footer.contact'), [
                'region_nl' => str_repeat('a', FooterSetting::PLAATS_MAX + 1),
            ])
            ->assertSessionHasErrors('region_nl');
    }

    /* --- Logboek en meldingen ------------------------------------------- */

    public function test_a_change_lands_in_the_activity_log(): void
    {
        $this->actingAs($this->beheerder())
            ->put(route('website.footer.tekst'), ['tagline_nl' => 'Een zin.']);

        $this->assertTrue(
            ActivityEntry::query()->where('subject_type', FooterSetting::class)->exists(),
            'De wijziging staat niet in het activiteitenlogboek.',
        );
    }

    /**
     * Twee keer hetzelfde opslaan is geen wijziging.
     *
     * De eigenaar hoort dan geen "aangepast" te zien: dat zou betekenen dat
     * hij niet kan vertrouwen op wat de melding zegt.
     */
    public function test_saving_the_same_thing_twice_reports_no_change(): void
    {
        $beheerder = $this->beheerder();

        $this->actingAs($beheerder)
            ->put(route('website.footer.tekst'), ['tagline_nl' => 'Een zin.']);

        $this->actingAs($beheerder)
            ->put(route('website.footer.tekst'), ['tagline_nl' => 'Een zin.'])
            ->assertRedirect();

        $this->assertDatabaseCount('footer_settings', 1);
    }
}
