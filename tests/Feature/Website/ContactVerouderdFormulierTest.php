<?php

namespace Tests\Feature\Website;

use App\Enums\ContactVeld;
use App\Enums\ContactVeldStatus;
use App\Mail\ContactBevestigingMail;
use App\Mail\ContactMessageMail;
use App\Models\ContactField;
use App\Models\ContactSubmission;
use App\Support\Contact\Contactformulier;
use Database\Seeders\ContactSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Honeypot\EncryptedTime;
use Tests\TestCase;

/**
 * Een formulier dat tijdens het invullen is gewijzigd.
 *
 * **Hier zaten twee fouten op elkaar gestapeld, en de tweede was de
 * ergste.**
 *
 * De eerste: de eigenaar kan een veld aanzetten terwijl een bezoeker zit te
 * typen. Dan mist de inzending dat veld en krijgt de bezoeker een
 * foutmelding bij een veld dat niet op zijn scherm staat. Daarvoor gaat er
 * een vingerafdruk van de instellingen mee.
 *
 * De tweede: die controle gaf een melding terug via `with('status')`, en dat
 * is hetzelfde kanaal als een geslaagde inzending. Het formulier toont
 * daarop het groene vak met "Aanvraag gelukt" en haalt zichzelf weg. De
 * bezoeker kreeg dus een vinkje te zien, zijn tekst verdween, en er was
 * niets opgeslagen en niets verstuurd.
 *
 * Nu is het een validatiefout op `instellingen`. Deze tests leggen vast dat
 * het dat blijft.
 *
 * Zie ContactRequest::after() en docs/architecture/modules/contact.md.
 */
class ContactVerouderdFormulierTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContactSeeder::class);
    }

    /**
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    private function inzending(array $extra = []): array
    {
        return array_merge([
            'name' => 'Kees Jansen',
            'email' => 'kees@example.com',
            'subject_text' => 'Vraag over een migratie',
            'message' => 'Graag advies over onze overstap naar een nieuwe omgeving.',
            config('honeypot.name_field_name') => '',
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->subMinute()),
        ], $extra);
    }

    /* --- De happy flow, als ijkpunt --------------------------------------- */

    /**
     * Een gewone inzending levert wél een successtatus op.
     *
     * Zonder dit ijkpunt bewijst de rest niets: een formulier dat nooit
     * succes meldt zou ook groen zijn.
     */
    public function test_a_normal_submission_does_set_the_success_status(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending(
            ['instellingen' => app(Contactformulier::class)->versie()],
        ))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', __('Bedankt voor je bericht. We nemen snel contact op.'));

        $this->assertSame(1, ContactSubmission::query()->count());
    }

    /* --- Het verouderde formulier ----------------------------------------- */

    /** Een oude vingerafdruk geeft een validatiefout en geen succes. */
    public function test_an_outdated_form_is_a_validation_error(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending(
            ['instellingen' => 'eenoudevingerafdruk'],
        ))->assertSessionHasErrors('instellingen');
    }

    /**
     * En vooral: geen successtatus.
     *
     * **Dit is de regressietest.** Stond de melding weer in `status`, dan
     * tekent het formulier zijn geluktvak en verdwijnt het formulier.
     */
    public function test_an_outdated_form_never_shows_the_success_box(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending(
            ['instellingen' => 'eenoudevingerafdruk'],
        ))->assertSessionMissing('status');
    }

    /** Er wordt niets opgeslagen en niets verstuurd. */
    public function test_an_outdated_form_stores_and_sends_nothing(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending(
            ['instellingen' => 'eenoudevingerafdruk'],
        ));

        $this->assertSame(0, ContactSubmission::query()->count());

        Mail::assertNotQueued(ContactMessageMail::class);
        Mail::assertNotQueued(ContactBevestigingMail::class);
        Mail::assertNothingSent();
    }

    /**
     * De melding zegt dat de tekst er nog staat.
     *
     * Inertia bewaart de ingevulde waarden bij een foutantwoord, dus dat is
     * waar -- en het is het enige dat een bezoeker op dat moment wil weten.
     */
    public function test_the_message_tells_the_visitor_the_text_is_still_there(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending(
            ['instellingen' => 'eenoudevingerafdruk'],
        ))->assertSessionHasErrors([
            'instellingen' => __('Het formulier is net aangepast terwijl je aan het typen was. Je tekst staat er nog -- kijk je even na of alles klopt en verstuur het opnieuw?'),
        ]);
    }

    /**
     * Opnieuw versturen lukt, met de verse vingerafdruk.
     *
     * Dat is de andere helft van "de bezoeker kan verder": een
     * waarschuwing waar je niet uit komt is net zo erg als geen
     * waarschuwing.
     */
    public function test_resubmitting_with_the_fresh_fingerprint_works(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending(
            ['instellingen' => 'eenoudevingerafdruk'],
        ))->assertSessionHasErrors('instellingen');

        $this->assertSame(0, ContactSubmission::query()->count());

        // De bezoeker ziet het formulier opnieuw, nu met de huidige
        // vingerafdruk erin, en drukt nog een keer op versturen.
        $this->post(route('contact.store'), $this->inzending(
            ['instellingen' => app(Contactformulier::class)->versie()],
        ))->assertSessionHasNoErrors();

        $this->assertSame(1, ContactSubmission::query()->count());
    }

    /**
     * En dit is het geval waar de hele vingerafdruk voor bestaat.
     *
     * De eigenaar zet een veld op verplicht terwijl de bezoeker zit te
     * typen. Zonder de vingerafdruk zou de bezoeker een foutmelding krijgen
     * bij een veld dat niet op zijn scherm staat.
     */
    public function test_a_field_that_becomes_required_mid_typing_gives_the_warning_and_not_a_field_error(): void
    {
        Mail::fake();

        $vingerafdruk = app(Contactformulier::class)->versie();

        // Ondertussen zet de eigenaar het telefoonnummer op verplicht.
        ContactField::query()
            ->where('key', ContactVeld::Telefoon)
            ->update(['status' => ContactVeldStatus::Verplicht]);

        $reactie = $this->post(route('contact.store'), $this->inzending(
            ['instellingen' => $vingerafdruk],
        ));

        $reactie->assertSessionHasErrors('instellingen');

        /*
         * Het veld zelf mag er ook rood van worden -- dat is eerlijk, het
         * is nu immers verplicht. Wat niet mag is dat dit als enige
         * foutmelding terugkomt, want dan staat er een rood veld dat de
         * bezoeker nooit heeft gezien zonder uitleg waarom.
         */
        $this->assertSame(0, ContactSubmission::query()->count());
    }

    /**
     * Zonder vingerafdruk blijft het formulier werken.
     *
     * Een inzending van buiten het formulier -- een test, een oud
     * script -- stuurt het veld niet mee. Dat is geen aanval en mag geen
     * waarschuwing opleveren.
     */
    public function test_a_submission_without_a_fingerprint_is_accepted(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status');

        $this->assertSame(1, ContactSubmission::query()->count());
    }

    /**
     * De honeypot gebruikt het statuskanaal wél, en dat moet zo blijven.
     *
     * Een bot mag geen verschil zien tussen betrapt en geslaagd. Dat is de
     * uitzondering die bewijst waarom de waarschuwing hierboven een ander
     * kanaal nodig had: `status` betekent "doe alsof het gelukt is", en dat
     * is precies niet wat je tegen een echte bezoeker wil zeggen.
     */
    public function test_the_honeypot_keeps_pretending_it_worked(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending([
            config('honeypot.name_field_name') => 'ik ben een bot',
        ]))->assertSessionHas('status', __('Bedankt voor je bericht. We nemen snel contact op.'));

        $this->assertSame(0, ContactSubmission::query()->count());
    }
}
