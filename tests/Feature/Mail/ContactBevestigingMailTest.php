<?php

namespace Tests\Feature\Mail;

use App\Mail\ContactBevestigingMail;
use App\Models\ContactSetting;
use Database\Seeders\ContactSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * De bevestiging aan de bezoeker, in beide talen.
 *
 * **Dit is de enige mail van dit project die in twee talen de deur uit
 * gaat.** De melding aan de eigenaar en de alarmeringsmails gaan altijd in
 * `config('app.locale')`, en dat is Nederlands.
 *
 * Daarom staat hier een test, en daarom is het misgegaan zonder test.
 * `->locale()` zette de taal goed en de tekst van de eigenaar is tweetalig,
 * maar de zinnen die wij erom heen zetten gaan door `__()` -- en drie
 * daarvan stonden niet in `lang/en.json`. Een Engelse bezoeker kreeg dus:
 *
 *     Bedankt voor je bericht          <- onze zin, geen vertaling
 *     Beste John Smith,                <- onze zin, geen vertaling
 *     Thank you for your message...    <- de tekst van de eigenaar, goed
 *     Je onderwerp: ...                <- onze zin, geen vertaling
 *
 * `TranslationsTest` kijkt alleen in `resources/js`, dus daar merkte niets
 * iets van: de sleutels staan in een Blade-bestand.
 *
 * Deze tests kijken naar de **gerenderde** mail en niet naar de
 * taalbestanden. Dat is het hele punt: een sleutel die bestaat maar niet
 * wordt gebruikt, of een `__()` die iemand weghaalt, verandert niets aan
 * een lijstvergelijking maar wel aan wat de bezoeker leest.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactBevestigingMailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ContactSeeder::class);
    }

    private function mail(string $taal): string
    {
        $instellingen = ContactSetting::huidige();

        $mail = new ContactBevestigingMail(
            naam: 'John Smith',
            onderwerp: $instellingen->bevestigingOnderwerp($taal),
            tekst: $instellingen->bevestigingTekst($taal),
            samenvatting: 'Informal conversation',
        );

        // Zoals ContactController het doet: de taal komt van de aanroeper,
        // want in de wachtrij bestaat de taal van het verzoek niet meer.
        $mail->locale($taal);

        return $mail->render();
    }

    /* --- Nederlands ------------------------------------------------------- */

    public function test_the_dutch_mail_is_dutch_from_top_to_bottom(): void
    {
        $html = $this->mail('nl');

        foreach ([
            'Bedankt voor je bericht',
            'Beste John Smith,',
            'Je onderwerp',
            'Met vriendelijke groet,',
            'Alle rechten voorbehouden.',
        ] as $zin) {
            $this->assertStringContainsString($zin, $html);
        }
    }

    /**
     * En er staat geen Engelse restregel in.
     *
     * "All rights reserved." kwam uit het sjabloon van Laravel zelf: een
     * Engelse brontekst, en onze vertaling loopt de andere kant op. Zie
     * resources/views/vendor/mail/html/message.blade.php.
     */
    public function test_the_dutch_mail_has_no_english_leftovers(): void
    {
        $html = $this->mail('nl');

        $this->assertStringNotContainsString('All rights reserved.', $html);
        $this->assertStringNotContainsString('Thank you for your message', $html);
    }

    /* --- Engels ----------------------------------------------------------- */

    public function test_the_english_mail_is_english_from_top_to_bottom(): void
    {
        $html = $this->mail('en');

        foreach ([
            'Thank you for your message',
            'Dear John Smith,',
            'Your subject',
            'Kind regards,',
            'All rights reserved.',
        ] as $zin) {
            $this->assertStringContainsString($zin, $html);
        }
    }

    /**
     * En geen Nederlandse restregel.
     *
     * Dit is de test die de fout zou hebben gevonden: alle vier deze zinnen
     * stonden in de Engelse mail.
     */
    public function test_the_english_mail_has_no_dutch_leftovers(): void
    {
        $html = $this->mail('en');

        foreach ([
            'Bedankt voor je bericht',
            'Beste John Smith,',
            'Je onderwerp',
            'Met vriendelijke groet,',
            'Alle rechten voorbehouden.',
        ] as $zin) {
            $this->assertStringNotContainsString(
                $zin,
                $html,
                "De Engelse bevestigingsmail bevat nog de Nederlandse zin \"{$zin}\". "
                    .'Zet hem in lang/en.json.',
            );
        }
    }

    /* --- Het onderwerp ---------------------------------------------------- */

    /**
     * Het onderwerp van de mail komt uit de instellingen en is dus van de
     * eigenaar -- in beide talen, en niet uit de code.
     */
    public function test_the_subject_comes_from_the_settings_in_both_languages(): void
    {
        $instellingen = ContactSetting::huidige();

        $instellingen->update([
            'confirmation_subject_nl' => 'Je bericht is binnen',
            'confirmation_subject_en' => 'Your message arrived',
        ]);

        $nederlands = new ContactBevestigingMail(
            naam: 'John Smith',
            onderwerp: $instellingen->fresh()->bevestigingOnderwerp('nl'),
            tekst: 'x',
            samenvatting: 'x',
        );

        $engels = new ContactBevestigingMail(
            naam: 'John Smith',
            onderwerp: $instellingen->fresh()->bevestigingOnderwerp('en'),
            tekst: 'x',
            samenvatting: 'x',
        );

        $this->assertSame('Je bericht is binnen', $nederlands->envelope()->subject);
        $this->assertSame('Your message arrived', $engels->envelope()->subject);
    }

    /**
     * Een leeg Engels onderwerp valt terug op het Nederlands.
     *
     * Een mail zonder onderwerp komt in een spammap terecht, en dat is
     * erger dan de verkeerde taal.
     */
    public function test_an_empty_english_subject_falls_back_to_dutch(): void
    {
        $instellingen = ContactSetting::huidige();

        $instellingen->update([
            'confirmation_subject_nl' => 'Je bericht is binnen',
            'confirmation_subject_en' => null,
        ]);

        $this->assertSame(
            'Je bericht is binnen',
            $instellingen->fresh()->bevestigingOnderwerp('en'),
        );
    }
}
