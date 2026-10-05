<?php

namespace Tests\Feature\Website;

use App\Models\ActivityEntry;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\User;
use Database\Seeders\ContactSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

/**
 * De onderwerpen van het contactformulier: aanmaken, wijzigen, verwijderen.
 *
 * Volgt de tabel uit docs/development/testen.md. Het bijzondere geval zit
 * onderaan: een onderwerp verwijderen waar aanvragen aan hangen mag, en die
 * aanvragen moeten leesbaar blijven.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactSubjectCrudTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(ContactSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoer(array $anders = []): array
    {
        return [
            'label_nl' => 'Vrijblijvend gesprek',
            'label_en' => 'Informal conversation',
            'published' => true,
            ...$anders,
        ];
    }

    private function maak(array $anders = []): TestResponse
    {
        return $this->actingAs($this->beheerder())
            ->post(route('website.contact.store'), $this->invoer($anders));
    }

    /* --- Rechten ---------------------------------------------------------- */

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $onderwerp = ContactSubject::factory()->create();

        $this->get(route('website.contact.index'))->assertRedirect(route('login'));
        $this->post(route('website.contact.store'), $this->invoer())->assertRedirect(route('login'));
        $this->put(route('website.contact.update', $onderwerp))->assertRedirect(route('login'));
        $this->patch(route('website.contact.online', $onderwerp))->assertRedirect(route('login'));
        $this->delete(route('website.contact.destroy', $onderwerp))->assertRedirect(route('login'));
        $this->put(route('website.contact.volgorde'))->assertRedirect(route('login'));
        $this->put(route('website.contact.velden'))->assertRedirect(route('login'));
        $this->put(route('website.contact.instellingen'))->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $zonder = User::factory()->create();
        $onderwerp = ContactSubject::factory()->create();

        $this->actingAs($zonder)->get(route('website.contact.index'))->assertForbidden();
        $this->actingAs($zonder)->post(route('website.contact.store'), $this->invoer())->assertForbidden();
        $this->actingAs($zonder)->put(route('website.contact.update', $onderwerp))->assertForbidden();
        $this->actingAs($zonder)->patch(route('website.contact.online', $onderwerp))->assertForbidden();
        $this->actingAs($zonder)->delete(route('website.contact.destroy', $onderwerp))->assertForbidden();
        $this->actingAs($zonder)->put(route('website.contact.velden'))->assertForbidden();
    }

    /* --- Het scherm ------------------------------------------------------- */

    public function test_the_screen_shows_the_subjects_and_the_fields(): void
    {
        ContactSubject::factory()->create(['label_nl' => 'Offerte']);

        $this->actingAs($this->beheerder())
            ->get(route('website.contact.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('website/Contact')
                ->has('onderwerpen', 1)
                ->where('onderwerpen.0.label_nl', 'Offerte')
                // Eén rij per veld uit de enum; zie ContactSeeder.
                ->has('velden', 6)
                ->has('instellingen')
                ->where('leeg', false));
    }

    public function test_the_screen_says_when_there_are_no_subjects(): void
    {
        $this->actingAs($this->beheerder())
            ->get(route('website.contact.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('leeg', true)
                ->has('onderwerpen', 0));
    }

    /* --- Aanmaken --------------------------------------------------------- */

    public function test_both_languages_are_saved(): void
    {
        $this->maak()->assertSessionHasNoErrors();

        $onderwerp = ContactSubject::query()->sole();

        $this->assertSame('Vrijblijvend gesprek', $onderwerp->label_nl);
        $this->assertSame('Informal conversation', $onderwerp->label_en);
        $this->assertTrue($onderwerp->published);
    }

    /** Een nieuw onderwerp komt achteraan in de lijst. */
    public function test_a_new_subject_goes_last(): void
    {
        ContactSubject::factory()->create(['position' => 7]);

        $this->maak();

        // `first()` en geen `sole()`: er staan er twee, en we willen de
        // nieuwste.
        $this->assertSame(8, ContactSubject::query()->latest('id')->first()?->position);
    }

    /**
     * Een leeg Engels veld wordt `null` en geen lege string.
     *
     * Dat onderscheid doet ertoe: het model beslist op `filled()` of het
     * Engels wordt gebruikt, en een lege string is gevuld -- dan krijgt een
     * Engelse bezoeker een lege regel in zijn keuzelijst.
     */
    public function test_an_empty_optional_field_becomes_null(): void
    {
        $this->maak(['label_en' => '   ']);

        $this->assertNull(ContactSubject::query()->sole()->label_en);
    }

    public function test_the_dutch_label_is_required(): void
    {
        $this->maak(['label_nl' => ''])->assertSessionHasErrors('label_nl');

        $this->assertSame(0, ContactSubject::query()->count());
    }

    public function test_a_label_that_is_too_long_is_refused(): void
    {
        $this->maak(['label_nl' => str_repeat('a', 81)])
            ->assertSessionHasErrors('label_nl');

        $this->maak(['label_en' => str_repeat('a', 81)])
            ->assertSessionHasErrors('label_en');
    }

    /* --- Wijzigen --------------------------------------------------------- */

    public function test_changing_one_subject_leaves_the_others_alone(): void
    {
        $eerste = ContactSubject::factory()->create(['label_nl' => 'Eerste']);
        $tweede = ContactSubject::factory()->create(['label_nl' => 'Tweede']);

        $this->actingAs($this->beheerder())
            ->put(route('website.contact.update', $eerste), $this->invoer([
                'label_nl' => 'Gewijzigd',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Gewijzigd', $eerste->fresh()->label_nl);
        $this->assertSame('Tweede', $tweede->fresh()->label_nl);
    }

    public function test_saving_without_a_change_says_so(): void
    {
        $onderwerp = ContactSubject::factory()->create([
            'label_nl' => 'Vrijblijvend gesprek',
            'label_en' => 'Informal conversation',
            'published' => true,
        ]);

        $this->actingAs($this->beheerder())
            ->put(route('website.contact.update', $onderwerp), $this->invoer());

        $this->assertSame(
            __('Er was niets veranderd.'),
            session('inertia.flash_data.toast.message'),
        );
    }

    /* --- Verwijderen ------------------------------------------------------ */

    public function test_deleting_leaves_the_rest_standing(): void
    {
        $weg = ContactSubject::factory()->create();
        $blijft = ContactSubject::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('website.contact.destroy', $weg));

        $this->assertModelMissing($weg);
        $this->assertModelExists($blijft);
    }

    /**
     * Een onderwerp verwijderen laat de aanvragen eraan leesbaar.
     *
     * **Dit is het geval waar het ontwerp om draait.** De aanvraag bewaart
     * de onderwerptekst zelf, zoals de bezoeker hem zag; de verwijzing
     * wordt leeg. Zou alleen de verwijzing bestaan, dan was de aanvraag na
     * het verwijderen een bericht zonder onderwerp.
     */
    public function test_deleting_a_subject_keeps_its_requests_readable(): void
    {
        $onderwerp = ContactSubject::factory()->create(['label_nl' => 'Offerte']);

        $aanvraag = ContactSubmission::factory()->create([
            'subject_id' => $onderwerp->id,
            'subject_text' => 'Offerte',
            'subject_custom' => false,
        ]);

        $this->actingAs($this->beheerder())
            ->delete(route('website.contact.destroy', $onderwerp));

        $aanvraag->refresh();

        $this->assertNull($aanvraag->subject_id);
        $this->assertSame('Offerte', $aanvraag->subject_text);

        // En het scherm kan het verschil zien tussen "zelf getypt" en
        // "koos iets dat inmiddels weg is".
        $this->assertTrue($aanvraag->onderwerpVerdwenen());
        $this->assertFalse($aanvraag->subject_custom);
    }

    /* --- Het logboek ------------------------------------------------------ */

    public function test_the_activity_log_records_the_change(): void
    {
        $voor = ActivityEntry::query()->count();

        $this->maak();

        $this->assertGreaterThan($voor, ActivityEntry::query()->count());
    }
}
