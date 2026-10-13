<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Models\ContactSubject;
use App\Models\ContactSubmission;
use App\Support\Security\SecurityLogger;
use App\Support\Toast;
use App\Support\Zoekterm;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Beheer → Aanvragen: wat er via het contactformulier binnenkwam.
 *
 * **Dit is geen mailprogramma.** De eigenaar antwoordt vanuit zijn eigen
 * postvak; dat was een uitdrukkelijke keuze. Dit scherm is er om bij te
 * houden wát er binnenkwam en wat hij er al mee heeft gedaan -- twee
 * schuifjes per aanvraag, gelezen en beantwoord, die hij zelf zet.
 *
 * **Het staat onder Beheer en niet onder Website**, want het is iets dat
 * je naslaat en niet iets dat je maakt. Het formulier zelf beheer je onder
 * Website → Contact.
 *
 * Een aanvraag verwijdert hij achter een verse 2FA-code: daar verdwijnen
 * gegevens door, en dat is precies waar `2fa.confirm` voor is. Hetzelfde
 * gebeurt op het scherm Juridisch, waar een verzoek van een bezoeker wordt
 * afgehandeld.
 *
 * Zie docs/architecture/modules/contact.md.
 */
class ContactSubmissionController extends Controller
{
    /** Hoeveel aanvragen er per pagina staan. */
    private const PER_PAGINA = 20;

    public function index(Request $request): Response
    {
        $zoek = $request->string('zoek')->trim()->toString();
        $stand = $request->string('stand')->trim()->toString();

        /*
         * Filteren op onderwerp.
         *
         * Een id, of `zelf` voor de aanvragen waarbij de bezoeker zijn
         * eigen onderwerp typte. Die laatste groep heeft geen id en zou
         * anders niet te filteren zijn -- en dat is juist een groep die je
         * apart wil kunnen bekijken: daar zit wat niet in de lijst van de
         * eigenaar past.
         */
        $onderwerp = $request->string('onderwerp')->trim()->toString();

        $aanvragen = ContactSubmission::query()
            ->nieuwsteEerst()
            ->when($zoek !== '', function ($query) use ($zoek) {
                /*
                 * Via Zoekterm, want `_` is in een LIKE "één willekeurig
                 * teken" en staat in heel veel e-mailadressen. Zonder
                 * ontsnappen vindt `jan_de_vries@...` ook het adres van
                 * iemand anders -- en dat zijn hier de gegevens van een
                 * derde. Zie AGENTS.md.
                 */
                $patroon = Zoekterm::patroon($zoek);
                $teken = Zoekterm::TEKEN;

                $query->where(fn ($q) => $q
                    ->whereRaw("name like ? escape '{$teken}'", [$patroon])
                    ->orWhereRaw("email like ? escape '{$teken}'", [$patroon])
                    ->orWhereRaw("company like ? escape '{$teken}'", [$patroon])
                    ->orWhereRaw("subject_text like ? escape '{$teken}'", [$patroon])
                    ->orWhereRaw("message like ? escape '{$teken}'", [$patroon])
                    /*
                     * En het onderwerp zelf, in beide talen.
                     *
                     * **Zonder dit zoek je op wat je ziet en vind je
                     * niets.** De regel toont de naam van het onderwerp in
                     * de taal van de eigenaar, terwijl `subject_text` de
                     * taal van de bezoeker bewaart; bij een Engelse
                     * bezoeker staan die twee dus los van elkaar. Zie
                     * ContactSubmission::onderwerpVoorDeEigenaar().
                     *
                     * De bewaarde tekst blijft erbij staan: die is het
                     * enige dat er is bij een zelf ingetypt of een
                     * inmiddels verwijderd onderwerp.
                     */
                    ->orWhereHas('subject', fn ($o) => $o
                        ->whereRaw("label_nl like ? escape '{$teken}'", [$patroon])
                        ->orWhereRaw("label_en like ? escape '{$teken}'", [$patroon])));
            })
            ->when($stand === 'ongelezen', fn ($query) => $query->ongelezen())
            ->when($stand === 'onbeantwoord', fn ($query) => $query->onbeantwoord())
            ->when($onderwerp === 'zelf', fn ($query) => $query->where('subject_custom', true))
            ->when(
                $onderwerp !== '' && $onderwerp !== 'zelf',
                fn ($query) => $query->where('subject_id', (int) $onderwerp),
            )
            /*
             * Het onderwerp meeladen: `voorHetScherm()` kijkt of het is
             * uitgelicht. Zonder dit is dat een query per regel, en op een
             * pagina van twintig aanvragen is dat twintig keer hetzelfde
             * vragen.
             */
            ->with('subject')
            ->paginate(self::PER_PAGINA)
            ->withQueryString()
            ->through(fn (ContactSubmission $aanvraag) => $aanvraag->voorHetScherm());

        return Inertia::render('admin/Aanvragen', [
            'aanvragen' => $aanvragen,
            'zoek' => $zoek,
            'stand' => $stand,
            'onderwerp' => $onderwerp,

            /*
             * De onderwerpen om op te filteren.
             *
             * **Álle onderwerpen en niet alleen die online staan.** Zette
             * de eigenaar er een offline, dan blijven de aanvragen die
             * eraan hangen bestaan -- en dan moet hij ze ook nog kunnen
             * opzoeken.
             *
             * **`naam()` en niet `label_nl`.** Hier stond het Nederlandse
             * label, met als reden "dit is zijn scherm". Dat klopt, maar
             * dat scherm volgt de taal uit zijn profiel en staat niet vast
             * op Nederlands. En belangrijker: de regels eronder tonen de
             * naam ook via `naam()`, dus met een vast label liepen de
             * keuzelijst en de regels uit elkaar zodra hij zijn portaal op
             * Engels zette.
             */
            'onderwerpen' => ContactSubject::query()
                ->opVolgorde()
                ->get()
                ->map(fn (ContactSubject $rij) => [
                    'id' => $rij->id,
                    'naam' => $rij->naam(),
                ])
                ->all(),

            'cijfers' => [
                'totaal' => ContactSubmission::query()->count(),
                'ongelezen' => ContactSubmission::query()->ongelezen()->count(),
                'onbeantwoord' => ContactSubmission::query()->onbeantwoord()->count(),
            ],

            'bewaartermijn' => (int) config('site.contact.retention_days'),
        ]);
    }

    /**
     * Gelezen of beantwoord aan- of uitzetten.
     *
     * Eén route voor allebei, want het is tweemaal hetzelfde: een
     * tijdstempel zetten of leegmaken. De eigenaar houdt dit zelf bij; de
     * applicatie raadt het niet. Een aanvraag die door het openklappen
     * vanzelf "gelezen" zou worden is een aanvraag die je kwijtraakt zodra
     * je per ongeluk op de verkeerde regel klikt.
     */
    public function stand(Request $request, ContactSubmission $submission): RedirectResponse
    {
        $gegevens = $request->validate([
            'wat' => ['required', 'string', 'in:gelezen,beantwoord'],
            'aan' => ['required', 'boolean'],
        ]);

        $kolom = $gegevens['wat'] === 'gelezen' ? 'read_at' : 'answered_at';
        $aan = (bool) $gegevens['aan'];

        $submission->update([$kolom => $aan ? now() : null]);

        /*
         * Beantwoord betekent dat je het ook gelezen hebt. Zonder deze
         * regel kun je een aanvraag als beantwoord markeren die nog
         * ongelezen heet, en dan telt het cijfer bovenaan iets dat niet
         * waar is.
         */
        if ($gegevens['wat'] === 'beantwoord' && $aan && ! $submission->gelezen()) {
            $submission->update(['read_at' => now()]);
        }

        return back();
    }

    /**
     * Een aanvraag verwijderen.
     *
     * Achter `2fa.confirm`, want hier verdwijnen gegevens van een
     * bezoeker. Het gaat in het beveiligingslogboek om dezelfde reden als
     * bij het wissen van mislukte mailpogingen: zonder die regel is er
     * later geen manier om te zien dat het is gebeurd.
     *
     * **Wat er in dat logboek komt is géén inhoud.** Alleen dat er één
     * aanvraag is verwijderd -- het bericht zelf mag niet via de
     * achterdeur in een ander logboek met een eigen bewaartermijn
     * terechtkomen.
     */
    public function destroy(ContactSubmission $submission, SecurityLogger $logboek): RedirectResponse
    {
        $submission->delete();

        $logboek->success(
            SecurityEventType::PrivacyDataCleared,
            user: request()->user(),
            context: ['wat' => 'contactaanvraag', 'aantal' => 1],
        );

        Toast::verwijderd(__('De aanvraag is verwijderd.'));

        return back();
    }
}
