<?php

namespace Tests\Feature\Mail;

use App\Mail\ContactBevestigingMail;
use App\Mail\ContactMessageMail;
use App\Mail\CrashAlertMail;
use App\Mail\SecurityAlertMail;
use App\Support\Security\Anomaly;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mail naar de eigenaar is altijd in de taal van de eigenaar.
 *
 * **De fout die dit afdekt was een val waar iedereen in loopt.** De
 * melding uit het contactformulier gebruikte `->locale(config('app.locale'))`
 * om Nederlands te forceren. Dat leest als "de ingestelde standaardtaal",
 * maar `App::setLocale()` schrijft de taal van het huidige verzoek ín die
 * configuratiewaarde -- zie `Application::setLocale()`. Na de middleware
 * `SetLocale` geeft `config('app.locale')` dus de taal van de bezoeker, en
 * kreeg de eigenaar "Contact form: ..." in zijn eigen postvak.
 *
 * Er zijn drie mails die naar de eigenaar gaan, en bij twee ervan draait de
 * afzender in een gewoon verzoek:
 *
 * | Mail                 | Vertrekt vanuit                        |
 * | -------------------- | -------------------------------------- |
 * | `ContactMessageMail` | het contactformulier -- een verzoek    |
 * | `CrashAlertMail`     | de foutafhandeling -- een verzoek      |
 * | `SecurityAlertMail`  | de planner -- geen verzoek             |
 *
 * Ze zetten hun taal nu zelf in hun constructor, uit `site.locale`. Daardoor
 * kan geen aanroeper het vergeten en hoeft niemand te weten dat
 * `app.locale` onderweg verandert.
 *
 * Zie config/site.php en docs/architecture/mail-en-queues.md.
 */
class InterneTaalTest extends TestCase
{
    use RefreshDatabase;

    /** De instelling zelf hoort een echte taal te zijn. */
    public function test_the_internal_language_is_a_known_language(): void
    {
        // De witte lijst is een kaart van code naar naam, dus de sleutels.
        $this->assertContains(
            config('site.locale'),
            array_keys((array) config('app.available_locales')),
            'site.locale moet een taal zijn die dit project kent.',
        );
    }

    /**
     * En hij is niet door een verzoek om te zetten.
     *
     * Dit is de kern. Zou iemand `site.locale` ooit uit `app.locale` laten
     * komen, dan valt deze test om.
     */
    public function test_a_visitor_cannot_change_the_internal_language(): void
    {
        $vooraf = config('site.locale');

        $this->withSession(['locale' => 'en'])->get(route('home'))->assertOk();

        $this->assertSame('en', app()->getLocale(), 'De testopzet klopt niet: de taal van het verzoek is niet omgezet.');
        $this->assertSame('en', config('app.locale'), 'app.locale hoort juist wél mee te gaan; dat is de val.');

        $this->assertSame($vooraf, config('site.locale'));
        $this->assertSame('nl', config('site.locale'));
    }

    /**
     * Elke mail aan de eigenaar draagt die taal, ook als hij in een Engels
     * verzoek wordt gemaakt.
     */
    public function test_every_internal_mail_carries_the_internal_language(): void
    {
        $this->withSession(['locale' => 'en'])->get(route('home'));

        // De taal van het verzoek staat nu op Engels; precies de situatie
        // waarin het eerder misging.
        $this->assertSame('en', app()->getLocale());

        $mails = [
            ContactMessageMail::class => new ContactMessageMail(
                senderName: 'John Smith',
                senderEmail: 'john@example.com',
                senderSubject: 'A question',
                body: 'Hello there.',
                senderLocale: 'en',
            ),
            CrashAlertMail::class => new CrashAlertMail(
                soort: 'RuntimeException',
                melding: 'iets ging mis',
                plek: 'app/Foo.php:1',
                adres: '/',
                gebruiker: null,
            ),
            SecurityAlertMail::class => new SecurityAlertMail(
                anomalies: [new Anomaly(
                    key: 'mail-problems',
                    title: 'Problemen met uitgaande mail',
                    count: 6,
                    threshold: 5,
                    details: [],
                )],
                since: now()->subHour(),
            ),
        ];

        foreach ($mails as $klasse => $mail) {
            $this->assertSame(
                'nl',
                $mail->locale,
                "{$klasse} hoort de taal van de eigenaar te dragen en niet die van het verzoek.",
            );
        }
    }

    /**
     * En het onderwerp komt er ook echt Nederlands uit.
     *
     * De taal op de mailable is het middel; dit is het doel. Een test op
     * alleen die eigenschap zou een mailable met een Nederlandse taal en
     * een Engels sjabloon goedkeuren.
     */
    public function test_the_rendered_subject_is_dutch(): void
    {
        $this->withSession(['locale' => 'en'])->get(route('home'));

        $mail = new ContactMessageMail(
            senderName: 'John Smith',
            senderEmail: 'john@example.com',
            senderSubject: 'A question',
            body: 'Hello there.',
            senderLocale: 'en',
        );

        // Zoals de wachtrij hem rendert: binnen de taal van de mailable.
        app()->setLocale((string) $mail->locale);

        $this->assertSame('Contactformulier: A question', $mail->envelope()->subject);
        $this->assertStringContainsString('Nieuw bericht via je website', $mail->render());
    }

    /**
     * De bevestiging aan de bezoeker is de uitzondering, en die moet
     * uitzondering blijven.
     *
     * Zou iemand daar ook een vaste taal in de constructor zetten, dan
     * krijgt een Engelse bezoeker een Nederlandse mail. Vandaar deze test
     * ernaast.
     */
    public function test_the_confirmation_has_no_fixed_language_of_its_own(): void
    {
        $mail = new ContactBevestigingMail(
            naam: 'John Smith',
            onderwerp: 'We have received your message',
            tekst: 'Thank you.',
            samenvatting: 'A question',
        );

        $this->assertNull(
            $mail->locale,
            'De bevestiging krijgt zijn taal van de aanroeper -- die van de bezoeker. Zie ContactController::verstuur().',
        );
    }
}
