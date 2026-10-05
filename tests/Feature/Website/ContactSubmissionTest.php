<?php

namespace Tests\Feature\Website;

use App\Mail\ContactBevestigingMail;
use App\Mail\ContactMessageMail;
use App\Models\ActivityEntry;
use App\Models\ContactSetting;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use Database\Seeders\ContactSeeder;
use Database\Seeders\PageSectionSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Spatie\Honeypot\EncryptedTime;
use Tests\TestCase;

/**
 * Wat er gebeurt als een bezoeker het contactformulier verstuurt.
 *
 * Drie dingen, in deze volgorde: de aanvraag wordt opgeslagen, de melding
 * gaat naar de eigenaar, en de bevestiging naar de bezoeker. Die volgorde
 * is geen toeval -- zie ContactController.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(PageSectionSeeder::class);
        $this->seed(ContactSeeder::class);
    }

    /**
     * @return array<string, mixed>
     */
    private function inzending(array $anders = []): array
    {
        return [
            'name' => 'Kees Jansen',
            'email' => 'kees@example.com',
            'subject_text' => 'Vraag over een migratie',
            'message' => 'Graag advies over onze overstap naar een nieuwe omgeving.',
            config('honeypot.name_field_name') => '',
            config('honeypot.valid_from_field_name') => EncryptedTime::create(now()->subMinute()),
            ...$anders,
        ];
    }

    /* --- Opslaan ---------------------------------------------------------- */

    public function test_the_request_is_saved(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending())
            ->assertSessionHasNoErrors();

        $aanvraag = ContactSubmission::query()->sole();

        $this->assertSame('Kees Jansen', $aanvraag->name);
        $this->assertSame('kees@example.com', $aanvraag->email);
        $this->assertSame('Vraag over een migratie', $aanvraag->subject_text);
        $this->assertStringContainsString('overstap', $aanvraag->message);

        // Nog niet gelezen en nog niet beantwoord: dat zet de eigenaar zelf.
        $this->assertFalse($aanvraag->gelezen());
        $this->assertFalse($aanvraag->beantwoord());
    }

    /**
     * De taal van de bezoeker wordt vastgelegd.
     *
     * **Hier en niet later**, want de wachtrij draait in een losse
     * opdrachtregel waar de taal van het verzoek niet meer bestaat. Zonder
     * deze kolom zou de bevestiging altijd Nederlands zijn.
     */
    public function test_the_language_of_the_visitor_is_recorded(): void
    {
        Mail::fake();

        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9'])
            ->post(route('contact.store'), $this->inzending());

        $this->assertSame('en', ContactSubmission::query()->sole()->locale);
    }

    /**
     * Er wordt geen IP-adres bewaard.
     *
     * Dat zou een tweede beveiligingslogboek maken met een andere termijn en
     * zonder doel. Een geblokkeerde poging staat al mét IP in
     * `security_events`.
     */
    public function test_no_ip_address_is_stored(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending());

        $kolommen = array_keys(ContactSubmission::query()->sole()->getAttributes());

        $this->assertNotContains('ip_address', $kolommen);
        $this->assertNotContains('user_agent', $kolommen);
    }

    /**
     * Een aanvraag komt niet in het activiteitenlogboek.
     *
     * **Dat is een bewuste keuze en geen vergetelheid.** `LogsActivity` zou
     * naam, adres en het volledige bericht een tweede keer vastleggen -- met
     * een eigen bewaartermijn, op een scherm dat niet over contact gaat, en
     * `redact()` in config/security.php vangt `message` niet af.
     */
    public function test_a_request_does_not_land_in_the_activity_log(): void
    {
        Mail::fake();

        $voor = ActivityEntry::query()->count();

        $this->post(route('contact.store'), $this->inzending());

        $this->assertSame($voor, ActivityEntry::query()->count());
    }

    /* --- Het onderwerp ---------------------------------------------------- */

    public function test_a_chosen_subject_is_linked_and_copied(): void
    {
        Mail::fake();

        $onderwerp = ContactSubject::factory()->create(['label_nl' => 'Offerte']);

        $this->post(route('contact.store'), $this->inzending([
            'subject_id' => $onderwerp->id,
        ]))->assertSessionHasNoErrors();

        $aanvraag = ContactSubmission::query()->sole();

        $this->assertSame($onderwerp->id, $aanvraag->subject_id);
        $this->assertSame('Offerte', $aanvraag->subject_text);
        $this->assertFalse($aanvraag->subject_custom);
    }

    /**
     * De onderwerptekst komt van de server en niet uit het verzoek.
     *
     * Anders kan iemand een geldig id meesturen met een eigen label erbij,
     * en staat er in het beheerscherm iets anders dan wat hij aanklikte.
     */
    public function test_the_subject_text_comes_from_the_server(): void
    {
        Mail::fake();

        $onderwerp = ContactSubject::factory()->create(['label_nl' => 'Offerte']);

        $this->post(route('contact.store'), $this->inzending([
            'subject_id' => $onderwerp->id,
            'subject_text' => 'Iets heel anders',
        ]));

        $this->assertSame('Offerte', ContactSubmission::query()->sole()->subject_text);
    }

    public function test_an_own_subject_is_marked_as_such(): void
    {
        Mail::fake();

        ContactSubject::factory()->create();

        $this->post(route('contact.store'), $this->inzending([
            'subject_text' => 'Iets heel anders',
        ]))->assertSessionHasNoErrors();

        $aanvraag = ContactSubmission::query()->sole();

        $this->assertNull($aanvraag->subject_id);
        $this->assertSame('Iets heel anders', $aanvraag->subject_text);
        $this->assertTrue($aanvraag->subject_custom);
    }

    public function test_an_unknown_subject_is_refused(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending([
            'subject_id' => 9999,
        ]))->assertSessionHasErrors('subject_id');

        $this->assertSame(0, ContactSubmission::query()->count());
    }

    /* --- De twee mails ---------------------------------------------------- */

    public function test_the_owner_gets_a_notice_and_the_visitor_a_confirmation(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending());

        /*
         * Het letterlijke adres en niet `config('mail.contact_address')`:
         * een vergelijking met de instelling die de code zelf gebruikt
         * slaagt ook als die instelling verkeerd staat. Dat het adres uit
         * de juiste bron komt is het werk van EmailAdressenTest.
         */
        Mail::assertQueued(ContactMessageMail::class, fn (ContactMessageMail $mail) => $mail->hasTo('info@atitadvies.nl'));

        Mail::assertQueued(ContactBevestigingMail::class, fn (ContactBevestigingMail $mail) => $mail->hasTo('kees@example.com'));
    }

    public function test_nothing_is_sent_synchronously(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending());

        // Wachten op de mailprovider mag een bezoeker nooit ophouden.
        Mail::assertNothingSent();
    }

    /**
     * De bevestiging gaat in de taal van de bezoeker.
     *
     * **De test die ertoe doet.** De wachtrij draait in een losse
     * opdrachtregel waar de taal altijd Nederlands is; vandaar
     * `Mail::to()->locale()` in de controller. Zou iemand dat weghalen, dan
     * krijgt een Engelse bezoeker een Nederlandse mail en valt deze test om.
     */
    public function test_the_confirmation_follows_the_language_of_the_visitor(): void
    {
        Mail::fake();

        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9'])
            ->post(route('contact.store'), $this->inzending());

        Mail::assertQueued(
            ContactBevestigingMail::class,
            fn (ContactBevestigingMail $mail) => $mail->locale === 'en',
        );
    }

    /**
     * En de melding aan de eigenaar blijft in zíjn taal.
     *
     * **Deze test vergeleek met `config('app.locale')` en kon daardoor niet
     * falen.** `App::setLocale()` schrijft de taal van het verzoek in
     * diezelfde configuratiewaarde, dus na een Engelstalig verzoek waren
     * beide kanten van de vergelijking `'en'` -- een groene test met de
     * naam "stays dutch" die het tegendeel toestond. En hij liet de fout
     * ook echt passeren: de eigenaar kreeg "Contact form: ..." in zijn
     * postvak.
     *
     * Daarom staat hier nu de letterlijke `'nl'`, en daarnaast het
     * gerenderde onderwerp. Dat tweede is wat de eigenaar echt ziet, en het
     * is onafhankelijk van hoe de taal intern wordt doorgegeven.
     */
    public function test_the_notice_to_the_owner_stays_dutch(): void
    {
        Mail::fake();

        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9'])
            ->post(route('contact.store'), $this->inzending());

        Mail::assertQueued(
            ContactMessageMail::class,
            function (ContactMessageMail $mail): bool {
                $this->assertSame('nl', $mail->locale);

                /*
                 * Zoals de wachtrij hem straks rendert: binnen de taal die
                 * op de mailable staat. Zonder deze stap meet je de taal
                 * van het verzoek dat net is afgelopen.
                 */
                app()->setLocale((string) $mail->locale);

                $this->assertSame(
                    'Contactformulier: Vraag over een migratie',
                    $mail->envelope()->subject,
                );

                return true;
            },
        );
    }

    /**
     * De taal van de bezoeker staat wél in de mail, als gegeven.
     *
     * De eigenaar moet weten in welke taal hij hoort te antwoorden. Dat is
     * iets anders dan de mail zélf in die taal zetten, en dat verschil is
     * precies waar het eerder misging.
     */
    public function test_the_notice_names_the_language_of_the_visitor(): void
    {
        Mail::fake();

        $this->withHeaders(['Accept-Language' => 'en-GB,en;q=0.9'])
            ->post(route('contact.store'), $this->inzending());

        Mail::assertQueued(
            ContactMessageMail::class,
            function (ContactMessageMail $mail): bool {
                $this->assertSame('en', $mail->senderLocale);

                app()->setLocale((string) $mail->locale);

                $this->assertStringContainsString('Engels', $mail->render());

                return true;
            },
        );
    }

    /** De tekst van de bevestiging komt uit de instellingen van de klant. */
    public function test_the_confirmation_uses_the_text_from_the_settings(): void
    {
        Mail::fake();

        ContactSetting::query()->first()?->update([
            'confirmation_body_nl' => 'Heel erg bedankt, ik kijk ernaar.',
        ]);

        $this->post(route('contact.store'), $this->inzending());

        Mail::assertQueued(
            ContactBevestigingMail::class,
            fn (ContactBevestigingMail $mail) => $mail->tekst === 'Heel erg bedankt, ik kijk ernaar.',
        );
    }

    /**
     * Er staat geen tekst van de bezoeker in het mailoverzicht.
     *
     * Dat de aanvraag nu wél wordt bewaard verandert hier niets aan: die
     * staat in `contact_submissions` met een eigen bewaartermijn, en het
     * mailoverzicht heeft het onderwerp daarnaast niet nodig.
     */
    public function test_the_mail_log_keeps_no_text_from_the_visitor(): void
    {
        $this->assertSame(
            __('Bericht via het contactformulier'),
            ContactMessageMail::logboekOnderwerp(),
        );

        $this->assertSame(
            __('Bevestiging aan een bezoeker'),
            ContactBevestigingMail::logboekOnderwerp(),
        );
    }

    /* --- De instellingen veranderen onderweg ------------------------------ */

    /**
     * Veranderen de instellingen terwijl de bezoeker zit te typen, dan komt
     * er één nette waarschuwing en geen foutmelding bij een veld dat hij
     * niet ziet.
     *
     * **Deze test verwachtte eerst `assertSessionHasNoErrors()` plus een
     * `status`.** Dat was het oude gedrag, en het was fout: `status` is het
     * kanaal van een geslaagde inzending, dus het formulier toonde "Aanvraag
     * gelukt" terwijl er niets was opgeslagen. Het volledige verhaal en de
     * rest van de gevallen staan in ContactVerouderdFormulierTest.
     */
    public function test_an_outdated_form_gets_one_calm_warning(): void
    {
        Mail::fake();

        $this->post(route('contact.store'), $this->inzending([
            'instellingen' => 'ietsheelanders',
        ]))
            ->assertSessionHasErrors('instellingen')
            ->assertSessionMissing('status');

        $this->assertSame(0, ContactSubmission::query()->count());
        Mail::assertNothingQueued();
    }
}
