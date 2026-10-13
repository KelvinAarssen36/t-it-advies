<?php

namespace Tests\Feature\Admin;

use App\Enums\SecurityEventType;
use App\Models\ContactSubmission;
use App\Models\SecurityEvent;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Het scherm Beheer → Aanvragen.
 *
 * Drie dingen: wie erbij mag, dat het zoeken niet meer teruggeeft dan je
 * vroeg, en dat de twee bollen doen wat ze zeggen.
 *
 * **Verwijderen zit achter een verse 2FA-code**, want daar verdwijnen
 * gegevens van een bezoeker door. Het aanvinken niet: dat is een kladblok.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactSubmissionScreenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function beheerder(): User
    {
        $user = User::factory()->create();
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * Een beheerder met een verse 2FA-bevestiging in de sessie.
     *
     * Nodig voor het verwijderen; zie de middleware `2fa.confirm` in
     * routes/admin.php.
     */
    private function bevestigdeBeheerder(): User
    {
        $beheerder = $this->beheerder();

        $google = new Google2FA;
        $secret = $google->generateSecretKey();

        $beheerder->forceFill([
            'two_factor_secret' => Fortify::currentEncrypter()->encrypt($secret),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->actingAs($beheerder)->post(route('security.two-factor.confirm.store'), [
            'code' => $google->getCurrentOtp($secret),
        ]);

        return $beheerder;
    }

    /* --- Rechten ---------------------------------------------------------- */

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $aanvraag = ContactSubmission::factory()->create();

        $this->get(route('admin.submissions.index'))->assertRedirect(route('login'));
        $this->patch(route('admin.submissions.stand', $aanvraag))->assertRedirect(route('login'));
        $this->delete(route('admin.submissions.destroy', $aanvraag))->assertRedirect(route('login'));
    }

    public function test_a_user_without_the_permission_is_refused(): void
    {
        $zonder = User::factory()->create();
        $aanvraag = ContactSubmission::factory()->create();

        $this->actingAs($zonder)->get(route('admin.submissions.index'))->assertForbidden();
        $this->actingAs($zonder)->patch(route('admin.submissions.stand', $aanvraag))->assertForbidden();
    }

    /* --- Het scherm ------------------------------------------------------- */

    public function test_the_screen_shows_the_requests_newest_first(): void
    {
        $oud = ContactSubmission::factory()->create([
            'name' => 'Oud',
            'created_at' => now()->subWeek(),
        ]);

        $nieuw = ContactSubmission::factory()->create(['name' => 'Nieuw']);

        $this->actingAs($this->beheerder())
            ->get(route('admin.submissions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/Aanvragen')
                ->has('aanvragen.data', 2)
                ->where('aanvragen.data.0.naam', 'Nieuw')
                ->where('aanvragen.data.1.naam', 'Oud')
                ->where('cijfers.totaal', 2)
                ->where('cijfers.ongelezen', 2)
                ->where('bewaartermijn', (int) config('site.contact.retention_days')));

        $this->assertModelExists($oud);
        $this->assertModelExists($nieuw);
    }

    public function test_the_screen_counts_what_still_needs_attention(): void
    {
        ContactSubmission::factory()->create();
        ContactSubmission::factory()->gelezen()->create();
        ContactSubmission::factory()->beantwoord()->create();

        $this->actingAs($this->beheerder())
            ->get(route('admin.submissions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('cijfers.totaal', 3)
                ->where('cijfers.ongelezen', 1)
                ->where('cijfers.onbeantwoord', 2));
    }

    /* --- Zoeken ----------------------------------------------------------- */

    public function test_searching_finds_a_request(): void
    {
        ContactSubmission::factory()->create(['email' => 'jan@example.com']);
        ContactSubmission::factory()->create(['email' => 'piet@example.com']);

        $this->actingAs($this->beheerder())
            ->get(route('admin.submissions.index', ['zoek' => 'jan@example.com']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('aanvragen.data', 1)
                ->where('aanvragen.data.0.email', 'jan@example.com'));
    }

    /**
     * Een underscore in een zoekterm is een letter en geen jokerteken.
     *
     * In een LIKE betekent `_` "één willekeurig teken", en underscores
     * staan in heel veel e-mailadressen. Zonder ontsnappen krijgt de
     * eigenaar de berichten van iemand anders te zien. Zie
     * App\Support\Zoekterm.
     */
    public function test_an_underscore_is_not_a_wildcard(): void
    {
        ContactSubmission::factory()->create(['email' => 'jan_de_vries@example.com']);
        ContactSubmission::factory()->create(['email' => 'janXdeYvries@example.com']);

        $this->actingAs($this->beheerder())
            ->get(route('admin.submissions.index', ['zoek' => 'jan_de_vries@example.com']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('aanvragen.data', 1)
                ->where('aanvragen.data.0.email', 'jan_de_vries@example.com'));
    }

    public function test_the_filter_shows_only_what_is_asked(): void
    {
        ContactSubmission::factory()->create(['name' => 'Ongelezen']);
        ContactSubmission::factory()->gelezen()->create(['name' => 'Gelezen']);

        $this->actingAs($this->beheerder())
            ->get(route('admin.submissions.index', ['stand' => 'ongelezen']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('aanvragen.data', 1)
                ->where('aanvragen.data.0.naam', 'Ongelezen'));
    }

    /* --- Gelezen en beantwoord -------------------------------------------- */

    public function test_marking_as_read_and_unread(): void
    {
        $aanvraag = ContactSubmission::factory()->create();

        $this->actingAs($this->beheerder())
            ->patch(route('admin.submissions.stand', $aanvraag), [
                'wat' => 'gelezen',
                'aan' => true,
            ]);

        $this->assertTrue($aanvraag->fresh()->gelezen());

        $this->actingAs($this->beheerder())
            ->patch(route('admin.submissions.stand', $aanvraag), [
                'wat' => 'gelezen',
                'aan' => false,
            ]);

        $this->assertFalse($aanvraag->fresh()->gelezen());
    }

    /**
     * Beantwoord zet gelezen mee aan.
     *
     * Je kunt niet iets beantwoorden dat je niet hebt gelezen, en anders
     * telt het cijfer bovenaan iets dat niet waar is.
     */
    public function test_marking_as_answered_also_marks_it_read(): void
    {
        $aanvraag = ContactSubmission::factory()->create();

        $this->actingAs($this->beheerder())
            ->patch(route('admin.submissions.stand', $aanvraag), [
                'wat' => 'beantwoord',
                'aan' => true,
            ]);

        $aanvraag->refresh();

        $this->assertTrue($aanvraag->beantwoord());
        $this->assertTrue($aanvraag->gelezen());
    }

    public function test_an_unknown_state_is_refused(): void
    {
        $aanvraag = ContactSubmission::factory()->create();

        $this->actingAs($this->beheerder())
            ->patch(route('admin.submissions.stand', $aanvraag), [
                'wat' => 'gearchiveerd',
                'aan' => true,
            ])
            ->assertSessionHasErrors('wat');
    }

    /**
     * Het openklappen zet "gelezen" niet vanzelf aan.
     *
     * Een aanvraag die je kwijtraakt omdat je per ongeluk op de verkeerde
     * regel klikte is erger dan een aanvraag die je twee keer openmaakt.
     * Dat het scherm het niet doet is hier niet te testen; dat de server
     * het niet doet wél.
     */
    public function test_opening_the_screen_marks_nothing_as_read(): void
    {
        ContactSubmission::factory()->create();

        $this->actingAs($this->beheerder())->get(route('admin.submissions.index'));

        $this->assertFalse(ContactSubmission::query()->sole()->gelezen());
    }

    /* --- Verwijderen ------------------------------------------------------ */

    public function test_deleting_needs_a_fresh_code(): void
    {
        $aanvraag = ContactSubmission::factory()->create();

        $this->actingAs($this->beheerder())
            ->delete(route('admin.submissions.destroy', $aanvraag));

        $this->assertModelExists($aanvraag);
    }

    public function test_a_request_can_be_deleted(): void
    {
        $aanvraag = ContactSubmission::factory()->create();
        $blijft = ContactSubmission::factory()->create();

        $this->actingAs($this->bevestigdeBeheerder())
            ->delete(route('admin.submissions.destroy', $aanvraag));

        $this->assertModelMissing($aanvraag);
        $this->assertModelExists($blijft);
    }

    /**
     * Het verwijderen gaat in het beveiligingslogboek.
     *
     * Zonder die regel is er later geen manier om te zien dat het is
     * gebeurd. **Wat erin komt is geen inhoud**: alleen dát er één aanvraag
     * is verwijderd -- het bericht mag niet via de achterdeur in een ander
     * logboek met een eigen bewaartermijn terechtkomen.
     */
    public function test_deleting_is_logged_without_the_message(): void
    {
        $aanvraag = ContactSubmission::factory()->create([
            'message' => 'Dit bericht mag nergens anders terechtkomen.',
        ]);

        $this->actingAs($this->bevestigdeBeheerder())
            ->delete(route('admin.submissions.destroy', $aanvraag));

        $regel = SecurityEvent::query()
            ->where('event', SecurityEventType::PrivacyDataCleared->value)
            ->latest('id')
            ->first();

        $this->assertNotNull($regel);

        $this->assertStringNotContainsString(
            'nergens anders terechtkomen',
            json_encode($regel->context) ?: '',
        );
    }
}
