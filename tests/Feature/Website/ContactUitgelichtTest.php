<?php

namespace Tests\Feature\Website;

use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Support\Contact\Contactformulier;
use Database\Seeders\ContactSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\SectionHeadingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * Een onderwerp uitlichten.
 *
 * De eigenaar wil soms één onderwerp naar voren halen -- spoed bijvoorbeeld,
 * of de dienst waar hij dat kwartaal op inzet. Dat is iets anders dan de
 * volgorde: bovenaan staan betekent "eerst", uitgelicht betekent "kijk
 * hier".
 *
 * **Een eigen vlag en geen plek in de volgorde**, want anders is hij zijn
 * volgorde kwijt zodra hij iets anders wil uitlichten. En op de site staat
 * het bóven de keuzelijst en niet als opgemaakte optie erin: in een
 * dichtgeklapte lijst is niets zichtbaar, dus daar valt niets uit te
 * lichten.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactUitgelichtTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(SectionHeadingSeeder::class);
        $this->seed(ContactSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /* --- Het beheerscherm -------------------------------------------------- */

    public function test_a_subject_can_be_created_as_featured(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.contact.store'), [
                'label_nl' => 'Storing of spoed',
                'published' => true,
                'featured' => true,
            ])->assertSessionHasNoErrors();

        $this->assertTrue(ContactSubject::query()->sole()->featured);
    }

    /** En standaard staat het uit: uitlichten hoort een keuze te zijn. */
    public function test_a_new_subject_is_not_featured_by_default(): void
    {
        $this->actingAs($this->beheerder())
            ->post(route('website.contact.store'), [
                'label_nl' => 'Gewoon een vraag',
                'published' => true,
            ])->assertSessionHasNoErrors();

        $this->assertFalse(ContactSubject::query()->sole()->featured);
    }

    public function test_it_can_be_switched_on_and_off_again(): void
    {
        $onderwerp = ContactSubject::factory()->create(['label_nl' => 'Offerte']);

        $this->actingAs($this->beheerder())
            ->put(route('website.contact.update', $onderwerp), [
                'label_nl' => 'Offerte',
                'featured' => true,
            ])->assertSessionHasNoErrors();

        $this->assertTrue($onderwerp->fresh()->featured);

        $this->actingAs($this->beheerder())
            ->put(route('website.contact.update', $onderwerp), [
                'label_nl' => 'Offerte',
                'featured' => false,
            ])->assertSessionHasNoErrors();

        $this->assertFalse($onderwerp->fresh()->featured);
    }

    /**
     * Uitlichten raakt de volgorde niet.
     *
     * Dat is de hele reden dat het een eigen vlag is en niet "zet hem
     * bovenaan".
     */
    public function test_featuring_does_not_touch_the_order(): void
    {
        $eerste = ContactSubject::factory()->create(['label_nl' => 'Eerste', 'position' => 1]);
        $tweede = ContactSubject::factory()->create(['label_nl' => 'Tweede', 'position' => 2]);

        $this->actingAs($this->beheerder())
            ->put(route('website.contact.update', $tweede), [
                'label_nl' => 'Tweede',
                'featured' => true,
            ]);

        $this->assertSame(1, $eerste->fresh()->position);
        $this->assertSame(2, $tweede->fresh()->position);
    }

    /** Het beheerscherm laat zien welke uitgelicht staan. */
    public function test_the_admin_screen_shows_which_are_featured(): void
    {
        ContactSubject::factory()->uitgelicht()->create(['label_nl' => 'Spoed']);
        ContactSubject::factory()->create(['label_nl' => 'Gewoon']);

        $this->actingAs($this->beheerder())
            ->get(route('website.contact.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $rijen = collect($page->toArray()['props']['onderwerpen']);

                $this->assertTrue($rijen->firstWhere('label_nl', 'Spoed')['featured']);
                $this->assertFalse($rijen->firstWhere('label_nl', 'Gewoon')['featured']);

                return true;
            });
    }

    /* --- Waar het voor is: het scherm Aanvragen ---------------------------- */

    /**
     * Een aanvraag met een uitgelicht onderwerp is als zodanig te zien.
     *
     * **Dit is waar uitlichten voor is.** De eigenaar zet een vinkje bij een
     * onderwerp en dan springt een bínnengekomen aanvraag met dat onderwerp
     * eruit in zijn postbus.
     */
    public function test_a_request_with_a_featured_subject_is_marked_on_the_screen(): void
    {
        $spoed = ContactSubject::factory()->uitgelicht()->create(['label_nl' => 'Spoed']);
        $gewoon = ContactSubject::factory()->create(['label_nl' => 'Gewoon']);

        ContactSubmission::factory()->create([
            'name' => 'Bij spoed',
            'subject_id' => $spoed->id,
            'subject_custom' => false,
        ]);

        ContactSubmission::factory()->create([
            'name' => 'Gewone vraag',
            'subject_id' => $gewoon->id,
            'subject_custom' => false,
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('admin.submissions.index'))
            ->assertInertia(function (AssertableInertia $page) {
                $rijen = collect($page->toArray()['props']['aanvragen']['data']);

                $this->assertTrue($rijen->firstWhere('naam', 'Bij spoed')['onderwerpUitgelicht']);
                $this->assertFalse($rijen->firstWhere('naam', 'Gewone vraag')['onderwerpUitgelicht']);

                return true;
            });
    }

    /**
     * Een zelf getypt onderwerp is nooit uitgelicht.
     *
     * Er hangt geen onderwerp van de eigenaar aan, dus er is niets om uit
     * te lichten. Zonder deze test zou een lege verwijzing stil als
     * "uitgelicht" kunnen doorkomen.
     */
    public function test_a_self_written_subject_is_never_marked(): void
    {
        ContactSubmission::factory()->create([
            'name' => 'Zelf getypt',
            'subject_id' => null,
            'subject_custom' => true,
        ]);

        $this->actingAs($this->beheerder())
            ->get(route('admin.submissions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aanvragen.data.0.onderwerpUitgelicht', false));
    }

    /**
     * En een verwijderd onderwerp ook niet.
     *
     * De eigenaar heeft het onderwerp zelf weggehaald; dan is er niets meer
     * uitgelicht. De aanvraag blijft leesbaar via zijn bewaarde
     * onderwerptekst.
     */
    public function test_a_deleted_subject_is_not_marked(): void
    {
        $onderwerp = ContactSubject::factory()->uitgelicht()->create(['label_nl' => 'Spoed']);

        $aanvraag = ContactSubmission::factory()->create([
            'name' => 'Oude aanvraag',
            'subject_id' => $onderwerp->id,
            'subject_text' => 'Spoed',
            'subject_custom' => false,
        ]);

        $onderwerp->delete();

        $this->assertFalse($aanvraag->fresh()->voorHetScherm()['onderwerpUitgelicht']);
        $this->assertSame('Spoed', $aanvraag->fresh()->voorHetScherm()['onderwerp']);
    }

    /* --- En niet op de site ----------------------------------------------- */

    /**
     * Uitlichten verandert niets voor de bezoeker.
     *
     * **Hier zat eerst een snelkeuze bóven de keuzelijst, en dat was een
     * verkeerde lezing van wat uitlichten is.** Het gaat over de postbus van
     * de eigenaar en niet over het formulier; dat laatste hoort een neutrale
     * lijst te blijven, want een onderwerp dat eruit springt duwt een
     * bezoeker een kant op die niet de zijne is.
     */
    public function test_featuring_changes_nothing_for_the_visitor(): void
    {
        ContactSubject::factory()->uitgelicht()->create(['label_nl' => 'Spoed']);

        $this->get(route('home'))->assertInertia(function (AssertableInertia $page) {
            $onderwerp = collect($page->toArray()['props']['contact']['onderwerpen'])
                ->firstWhere('naam', 'Spoed');

            $this->assertSame(['id', 'naam'], array_keys((array) $onderwerp));

            return true;
        });
    }

    /** En het raakt de vingerafdruk van het formulier dus ook niet. */
    public function test_featuring_does_not_change_the_fingerprint_of_the_form(): void
    {
        $onderwerp = ContactSubject::factory()->create(['label_nl' => 'Offerte']);

        $voor = app(Contactformulier::class)->versie();

        $onderwerp->update(['featured' => true]);

        $na = app(Contactformulier::class)->versie();

        $this->assertSame($voor, $na);
    }

    /**
     * Een uitgelicht onderwerp dat offline staat komt niet op de site.
     *
     * Uitlichten is geen manier om `published` te omzeilen.
     */
    public function test_a_featured_subject_that_is_offline_stays_off_the_site(): void
    {
        ContactSubject::factory()->uitgelicht()->offline()->create(['label_nl' => 'Verborgen']);

        $this->get(route('home'))->assertInertia(function (AssertableInertia $page) {
            $namen = collect($page->toArray()['props']['contact']['onderwerpen'])
                ->pluck('naam');

            $this->assertFalse($namen->contains('Verborgen'));

            return true;
        });
    }
}
