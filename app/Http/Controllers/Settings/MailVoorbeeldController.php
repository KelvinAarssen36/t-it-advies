<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Mail\ContactBevestigingMail;
use App\Models\ContactSetting;
use App\Models\SiteSetting;
use Illuminate\Http\Response;
use Illuminate\Mail\Markdown;

/**
 * Hoe een mail van deze website eruitziet.
 *
 * Twee voorbeelden, en het verschil tussen die twee is de hele reden dat ze
 * er allebei zijn:
 *
 * 1. **De bevestiging** -- de échte mailable, met de tekst van de eigenaar
 *    erin en verzonnen gegevens eromheen. Dit is wat een bezoeker krijgt.
 * 2. **De onderdelen** -- een demo van de bouwstenen die in zijn ándere
 *    mails voorkomen: een knop, een uitgelicht vak, een tabel.
 *
 * **Dat tweede is er omdat het eerste misleidend was zonder.** De
 * bevestiging heeft geen knop, dus zag de eigenaar nooit hoe een knop in
 * zijn huisstijl eruitziet -- terwijl zijn beveiligingsmelding er wel een
 * heeft. Een knop in de bevestiging plakken zou erger zijn: dan toont het
 * voorbeeld een mail die niet bestaat.
 *
 * **Dit rendert de echte mailable en geen nabootsing.** Hetzelfde sjabloon
 * en hetzelfde thema als wat er werkelijk uitgaat. Daardoor kan het
 * voorbeeld niet uit de pas lopen: zou het een eigen stukje HTML zijn, dan
 * klopt het tot de dag dat iemand het thema aanpast en er niet aan denkt.
 *
 * **Geen Inertia maar kale HTML.** Het scherm Weergave zet dit in een
 * `iframe`, zodat de stijlen van de mail niets met die van het portaal te
 * maken hebben -- een mail heeft eigen opmaak in de tags zelf, en die zou
 * anders met het portaal vechten.
 *
 * Zie docs/architecture/mail-en-queues.md en
 * docs/architecture/modules/contact.md.
 */
class MailVoorbeeldController extends Controller
{
    /** De bevestiging die een bezoeker krijgt. */
    public function bevestiging(): Response
    {
        $instellingen = ContactSetting::huidige();
        $taal = app()->getLocale();

        $mail = new ContactBevestigingMail(
            /*
             * Een verzonnen naam, en bewust niet door `__()`: een eigennaam
             * heeft één waarde en geen twee. Zo staat het in
             * docs/architecture/vertalingen.md.
             */
            naam: 'Jan de Vries',

            onderwerp: $instellingen->bevestigingOnderwerp($taal),
            tekst: $instellingen->bevestigingTekst($taal),

            /*
             * Het onderwerp wél, want dat is een voorbeeld van wat de
             * eigenaar zelf instelt -- en in de Engelse versie van het
             * voorbeeld hoort daar Engels te staan.
             */
            samenvatting: __('Vrijblijvend gesprek'),
        );

        return $this->antwoord($mail->render());
    }

    /**
     * De bouwstenen die in de andere mails voorkomen.
     *
     * Door hetzelfde sjabloon en hetzelfde thema, dus de knop die hier
     * staat is letterlijk de knop uit de beveiligingsmelding. Het is geen
     * echte mail en het scherm zegt dat er ook bij -- een voorbeeld dat
     * zich voordoet als post die je kunt krijgen is misleidend.
     */
    public function onderdelen(): Response
    {
        /*
         * Via `Markdown` en niet via een mailable of `Mail::render()`.
         *
         * Een mailable zou een klasse zijn die nooit iets verstuurt, en
         * `Mail::render()` laat de Blade door zonder het thema in te
         * voegen -- dan zie je de opbouw zonder de opmaak, en dat is
         * precies het verkeerde voorbeeld. `Markdown` is de renderer die
         * elke markdown-mailable ook gebruikt.
         *
         * **Het thema wordt hier expliciet gezet.** Het exemplaar uit de
         * container draagt `config('mail.markdown.theme')`, en dat is de
         * terugval en niet de keuze van de eigenaar. Zonder deze regel zou
         * het voorbeeld in de lichte stijl blijven staan nadat hij de
         * huisstijl heeft gekozen -- een voorbeeld dat liegt is erger dan
         * geen voorbeeld.
         */
        $html = app(Markdown::class)
            ->theme(SiteSetting::mailstijl()->thema())
            ->render('mail.voorbeeld-onderdelen');

        return $this->antwoord((string) $html);
    }

    private function antwoord(string $html): Response
    {
        return response($html)
            ->header('Content-Type', 'text/html; charset=utf-8')

            /*
             * Niet bewaren. Past de eigenaar zijn tekst aan en ververst hij
             * het scherm, dan hoort hij de nieuwe te zien -- en een
             * voorbeeld dat achterloopt is erger dan geen voorbeeld.
             */
            ->header('Cache-Control', 'no-store');
    }
}
