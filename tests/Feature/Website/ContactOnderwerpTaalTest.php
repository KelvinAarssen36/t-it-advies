<?php

namespace Tests\Feature\Website;

use App\Mail\ContactBevestigingMail;
use App\Mail\ContactMessageMail;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Models\User;
use Database\Seeders\ContactSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia;
use Spatie\Honeypot\EncryptedTime;
use Tests\TestCase;

/**
 * In welke taal de naam van een onderwerp op het scherm komt.
 *
 * Een onderwerp heeft twee namen, en er zijn drie lezers: de bezoeker, de
 * eigenaar in zijn postbus, en het archief. Die hoeven niet hetzelfde te
 * zien, en deden dat ook niet -- maar wel consequent:
 *
 * | Wie                      | Welke naam                              |
 * | ------------------------ | --------------------------------------- |
 * | De bezoeker              | Zijn eigen taal                         |
 * | De bevestiging aan hem   | Zijn eigen taal                         |
 * | Het archief (`subject_text`) | Zijn eigen taal, zoals hij het zag |
 * | De eigenaar              | De taal van zijn portaal                |
 *
 * **Die laatste regel was de fout.** De eigenaar kreeg de bewaarde tekst
 * te zien, dus bij een Engelse bezoeker stond er "Informal conversation"
 * midden in zijn Nederlandse postbus -- terwijl de keuzelijst om op te
 * filteren voor datzelfde onderwerp "Vrijblijvend gesprek" zei. Dan zoek
 * je op wat je ziet en vind je niets.
 *
 * Zie ContactSubmission::onderwerpVoorDeEigenaar() en
 * docs/architecture/modules/contact.md.
 */
class ContactOnderwerpTaalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(ContactSeeder::class);
    }

    private function onderwerp(?string $engels = 'Informal conversation'): ContactSubject
    {
        return ContactSubject::query()->create([
            'label_nl' => 'Vrijblijvend gesprek',
            'label_en' => $engels,
            'published' => true,
            'position' => 1,
        ]);
    }

    /**
     * De eigenaar, met Nederlands in zijn profiel.
     *
     * Dat profiel is hier geen detail. Zonder taal in het profiel en zonder
     * `Accept-Language` valt `SetLocale` terug op Engels -- met opzet, zodat
     * een Duitse of Franse bezoeker Engels krijgt in plaats van Nederlands.
     * In een test betekent dat: zet je de taal niet, dan meet je een Engels
     * portaal en niet dat van de eigenaar.
     */
    private function eigenaar(string $taal = 'nl'): User
    {
        $user = User::factory()->create(['locale' => $taal]);
        $user->givePermissionTo('manage portal');

        return $user;
    }

    /**
     * @param  array<string, mixed>  $anders
     * @return array<string, mixed>
     */
    private function inzending(array $anders = []): array
    {
        return [
            'name' => 'John Smith',
            'email' => 'john@voorbeeld.test',
            'message' => 'Could you give me an indication of the cost?',
            config('honeypot.name_field_name') => '',
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->subMinute()),
            ...$anders,
        ];
    }

    /** Een bezoeker met een Engelse browser stuurt het formulier in. */
    private function engelseBezoeker(array $inzending): void
    {
        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9'])
            ->post(route('contact.store'), $this->inzending($inzending))
            ->assertSessionHasNoErrors();

        /*
         * De koppen blijven anders staan voor élk volgend verzoek in deze
         * test, en dan opent de eigenaar zijn portaal ook in het Engels.
         */
        $this->flushHeaders();
    }

    /* --- De bezoeker ------------------------------------------------------- */

    /*
     * **Wat het formulier aan de bezoeker toont staat hier niet.** Dat is
     * de kant die altijd goed was, en ContactPlacementTest legt hem al
     * vast: `test_an_english_visitor_gets_the_english_subject` en
     * `test_a_missing_english_name_falls_back_to_dutch`. Dit bestand gaat
     * over de kant die níet goed was -- die van de eigenaar.
     */

    /**
     * Het archief bewaart wat de bezoeker zág.
     *
     * Niet wat de eigenaar leest: dit is het enige dat overblijft als het
     * onderwerp later wordt verwijderd, en dan hoort er te staan wat er op
     * dat moment op het formulier stond.
     */
    public function test_the_archive_keeps_what_the_visitor_saw(): void
    {
        Mail::fake();

        $onderwerp = $this->onderwerp();

        $this->engelseBezoeker(['subject_id' => $onderwerp->id]);

        $aanvraag = ContactSubmission::query()->sole();

        $this->assertSame('en', $aanvraag->locale);
        $this->assertSame('Informal conversation', $aanvraag->subject_text);
    }

    /** En zijn bevestiging gebruikt diezelfde naam. */
    public function test_the_confirmation_uses_the_visitors_name(): void
    {
        Mail::fake();

        $onderwerp = $this->onderwerp();

        $this->engelseBezoeker(['subject_id' => $onderwerp->id]);

        Mail::assertQueued(
            ContactBevestigingMail::class,
            fn (ContactBevestigingMail $mail) => $mail->samenvatting === 'Informal conversation'
                && $mail->locale === 'en',
        );
    }

    /* --- De eigenaar ------------------------------------------------------- */

    /**
     * Zijn melding staat in zijn eigen taal.
     *
     * De taal staat expliciet in de aanroep, want de mail wordt opgebouwd
     * tijdens het verzoek van de bezoeker: daar is `app()->getLocale()` nog
     * de zijne.
     */
    public function test_the_notice_to_the_owner_uses_his_own_language(): void
    {
        Mail::fake();

        $onderwerp = $this->onderwerp();

        $this->engelseBezoeker(['subject_id' => $onderwerp->id]);

        Mail::assertQueued(
            ContactMessageMail::class,
            fn (ContactMessageMail $mail) => $mail->senderSubject === 'Vrijblijvend gesprek'
                && $mail->locale === 'nl',
        );
    }

    /** En de regel én de keuzelijst op zijn scherm zeggen hetzelfde. */
    public function test_the_screen_and_the_filter_agree(): void
    {
        Mail::fake();

        $onderwerp = $this->onderwerp();

        $this->engelseBezoeker(['subject_id' => $onderwerp->id]);

        $this->actingAs($this->eigenaar())
            ->get(route('admin.submissions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aanvragen.data.0.onderwerp', 'Vrijblijvend gesprek')
                ->where('onderwerpen.0.naam', 'Vrijblijvend gesprek'));
    }

    /** Zet hij zijn portaal op Engels, dan gaan ze samen mee. */
    public function test_an_english_portal_moves_both(): void
    {
        Mail::fake();

        $onderwerp = $this->onderwerp();

        $this->engelseBezoeker(['subject_id' => $onderwerp->id]);

        $this->actingAs($this->eigenaar('en'))
            ->get(route('admin.submissions.index'))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->where('aanvragen.data.0.onderwerp', 'Informal conversation')
                ->where('onderwerpen.0.naam', 'Informal conversation'));
    }

    /* --- Waar er niets te vertalen valt ------------------------------------ */

    /**
     * Een zelf ingetypt onderwerp blijft staan zoals het is getypt.
     *
     * Wat iemand zelf schrijft vertalen we nooit; er is ook geen onderwerp
     * om het uit te halen.
     */
    public function test_a_self_typed_subject_is_never_translated(): void
    {
        Mail::fake();

        $this->engelseBezoeker(['subject_text' => 'Something else entirely']);

        $aanvraag = ContactSubmission::query()->sole();

        $this->assertTrue($aanvraag->subject_custom);
        $this->assertSame('Something else entirely', $aanvraag->onderwerpVoorDeEigenaar('nl'));
    }

    /** Is het onderwerp verwijderd, dan is de bewaarde tekst het enige dat er is. */
    public function test_a_deleted_subject_falls_back_to_the_archive(): void
    {
        Mail::fake();

        $onderwerp = $this->onderwerp();

        $this->engelseBezoeker(['subject_id' => $onderwerp->id]);

        $onderwerp->delete();

        $aanvraag = ContactSubmission::query()->sole()->fresh();

        $this->assertTrue($aanvraag->onderwerpVerdwenen());
        $this->assertSame('Informal conversation', $aanvraag->onderwerpVoorDeEigenaar('nl'));
    }

    /* --- Zoeken ------------------------------------------------------------ */

    /**
     * Zoeken op wat je ziet vindt het ook.
     *
     * Dit is het gevolg dat makkelijk wordt vergeten: het scherm toont de
     * Nederlandse naam, terwijl in de tabel de Engelse staat. Zonder de
     * zoekopdracht op het onderwerp zelf zou de eigenaar "vrijblijvend"
     * typen en niets vinden.
     */
    public function test_searching_for_what_you_see_finds_it(): void
    {
        Mail::fake();

        $onderwerp = $this->onderwerp();

        $this->engelseBezoeker(['subject_id' => $onderwerp->id]);

        $this->actingAs($this->eigenaar())
            ->get(route('admin.submissions.index', ['zoek' => 'vrijblijvend']))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->has('aanvragen.data', 1)
                ->where('aanvragen.data.0.onderwerp', 'Vrijblijvend gesprek'));
    }

    /** En de bewaarde tekst blijft vindbaar, voor een zelf getypt onderwerp. */
    public function test_the_archived_text_stays_searchable(): void
    {
        Mail::fake();

        $this->engelseBezoeker(['subject_text' => 'Something else entirely']);

        $this->actingAs($this->eigenaar())
            ->get(route('admin.submissions.index', ['zoek' => 'something else']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('aanvragen.data', 1));
    }

    /** Een zoekterm die bij geen van beide talen past vindt niets. */
    public function test_a_term_that_matches_neither_finds_nothing(): void
    {
        Mail::fake();

        $onderwerp = $this->onderwerp();

        $this->engelseBezoeker(['subject_id' => $onderwerp->id]);

        $this->actingAs($this->eigenaar())
            ->get(route('admin.submissions.index', ['zoek' => 'offerte']))
            ->assertInertia(fn (AssertableInertia $page) => $page->has('aanvragen.data', 0));
    }
}
