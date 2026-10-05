<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactRequest;
use App\Mail\ContactBevestigingMail;
use App\Mail\ContactMessageMail;
use App\Models\ContactSetting;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Support\Bezoek\Bezoekteller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

/**
 * Neemt berichten van het contactformulier aan.
 *
 * De keten op de route is: rate limiting -> honeypot -> validatie met
 * Turnstile -> opslaan -> queue. Zie routes/web.php en
 * docs/security/spam-en-botbescherming.md.
 *
 * **De volgorde van opslaan en mailen is geen toeval.** Eerst de aanvraag
 * in de database, dan pas de mails in de wachtrij. Andersom zou de eigenaar
 * een mail kunnen krijgen waar geen regel in zijn overzicht bij hoort, en
 * dan zoekt hij naar iets dat er niet is.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactController extends Controller
{
    public function __construct(private readonly Bezoekteller $teller) {}

    public function store(ContactRequest $request): RedirectResponse
    {
        /*
         * Eén exemplaar van het formulier voor dit hele verzoek, en dat is
         * het exemplaar waarmee de validatie heeft gewerkt.
         *
         * **Dat is geen zuinigheid maar consistentie.** Zou de controller
         * zijn eigen exemplaar oplossen, dan valideert hij tegen de ene
         * momentopname en schrijft hij `shown` uit de andere -- en dan kan
         * er in theorie een aanvraag ontstaan waarvan de bewaarde veldlijst
         * niet klopt met waarop is gecontroleerd. Nu kan dat niet. Het
         * scheelt en passant de dubbele query naar `contact_fields`.
         *
         * Zie ContactRequest::formulier().
         */
        $formulier = $request->formulier();

        /*
         * Staat het onderdeel uit, dan bestaat dit adres niet.
         *
         * **Dit ontbrak.** Het formulier verdween netjes van de site zodra
         * de eigenaar het onderdeel uitzette, maar deze route bleef
         * aannemen, opslaan en mailen -- een oud tabblad of een bot met de
         * URL kwam er dus nog door. Een instelling die alleen de voorkant
         * verandert is geen instelling.
         */
        abort_unless($formulier->staatAan(), 404);

        /*
         * De controle op een verouderd formulier staat niet meer hier maar
         * in de validatie; zie ContactRequest::after(). Dat was nodig: als
         * waarschuwing via `with('status')` kwam hij terecht in het vak dat
         * "Aanvraag gelukt" toont, en verdween het formulier met de tekst
         * van de bezoeker erin terwijl er níets was opgeslagen.
         */
        $validated = $request->safe();

        $onderwerp = $validated->integer('subject_id') > 0
            ? ContactSubject::query()->find($validated->integer('subject_id'))
            : null;

        $aanvraag = ContactSubmission::query()->create([
            /*
             * De taal waarin de bezoeker het formulier zag. Vastleggen
             * móét hier gebeuren: de queue draait straks in een losse
             * opdrachtregel waar `SetLocale` nooit heeft gedraaid, en daar
             * is de taal altijd Nederlands.
             */
            'locale' => app()->getLocale(),

            'name' => $validated->string('name')->toString(),
            'email' => $validated->string('email')->toString(),
            'company' => $this->leegIsNull($validated->string('company')->toString()),
            'phone' => $this->leegIsNull($validated->string('phone')->toString()),

            'subject_id' => $onderwerp?->id,

            /*
             * De tekst van het onderwerp zoals de bezoeker hem zag.
             *
             * **Bij een gekozen onderwerp komt die van de server en niet
             * uit het verzoek.** Anders kan iemand een geldig id meesturen
             * met een eigen label erbij, en staat er in het beheerscherm
             * iets anders dan wat hij aanklikte.
             */
            'subject_text' => $onderwerp?->naam()
                ?? $validated->string('subject_text')->trim()->toString(),

            'subject_custom' => $onderwerp === null,

            'message' => $validated->string('message')->toString(),
            'shown' => $formulier->aanstaandeVelden(),
        ]);

        $this->verstuur($aanvraag);

        /*
         * Eén bij de dagteller, en niets van het bericht zelf.
         *
         * Dit is het enige cijfer dat zegt of de site zijn wérk doet in
         * plaats van alleen bekeken te worden. Zie
         * docs/architecture/bezoekcijfers.md.
         */
        $this->teller->telContact();

        return back()->with('status', __('Bedankt voor je bericht. We nemen snel contact op.'));
    }

    /**
     * De twee mails, elk in zijn eigen taal.
     *
     * De melding gaat naar de eigenaar en hoort in zíjn taal; de
     * bevestiging gaat naar de bezoeker en hoort in de taal waarin die het
     * formulier invulde.
     *
     * **Alleen de bevestiging krijgt hier een taal mee, en dat is met
     * opzet.** `ContactMessageMail` zet zijn eigen taal in de constructor,
     * zodat geen enkele aanroeper hem kan vergeten. Hier stond eerder
     * `->locale(config('app.locale'))` om Nederlands te forceren, en dat
     * deed niets: `App::setLocale()` schrijft de taal van het verzoek in
     * diezelfde configuratiewaarde. Zie config/site.php.
     */
    private function verstuur(ContactSubmission $aanvraag): void
    {
        Mail::to(config('mail.contact_address', config('mail.from.address')))
            ->queue(new ContactMessageMail(
                senderName: $aanvraag->name,
                senderEmail: $aanvraag->email,
                /*
                 * Het onderwerp in de taal van de eigenaar, en niet de
                 * bewaarde tekst. Die staat in de taal van de bezoeker, en
                 * dan kreeg hij een Nederlandse mail met "Contactformulier:
                 * Informal conversation" erboven. De taal staat er
                 * expliciet bij omdat dit nog tijdens het verzoek van de
                 * bezoeker wordt opgebouwd: `app()->getLocale()` is hier
                 * nog de zijne. Zie
                 * ContactSubmission::onderwerpVoorDeEigenaar().
                 */
                senderSubject: $aanvraag->onderwerpVoorDeEigenaar(
                    config('site.locale'),
                ),
                body: $aanvraag->message,
                senderCompany: $aanvraag->company,
                senderPhone: $aanvraag->phone,
                senderLocale: $aanvraag->locale,
            ));

        $instellingen = ContactSetting::huidige();

        Mail::to($aanvraag->email)
            ->locale($aanvraag->locale)
            ->queue(new ContactBevestigingMail(
                naam: $aanvraag->name,
                onderwerp: $instellingen->bevestigingOnderwerp($aanvraag->locale),
                tekst: $instellingen->bevestigingTekst($aanvraag->locale),
                samenvatting: $aanvraag->subject_text,
            ));
    }

    /**
     * Een leeg optioneel veld wordt `null` en geen lege string.
     *
     * Hetzelfde als `SchoneVelden` doet in de beheerschermen: zonder dit
     * staat er in de database het verschil tussen "niet ingevuld" en
     * "ingevuld met niets", en dat verschil bestaat voor een lezer niet.
     */
    private function leegIsNull(string $waarde): ?string
    {
        $schoon = trim($waarde);

        return $schoon === '' ? null : $schoon;
    }
}
