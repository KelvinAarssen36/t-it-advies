<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Support\Juridisch\Antwoordtekst;
use App\Support\Juridisch\Gegevensoverzicht;
use App\Support\Security\SecurityLogger;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het scherm Juridisch: verzoeken van bezoekers afhandelen.
 *
 * **Dit scherm is er op verzoek van de eigenaar, en met één deel er
 * bewust uit.** Hij vroeg om "een aparte pagina waar al dat soort dingen
 * geregeld kunnen worden, zoals verwijderen van gegevens". Zoeken, een
 * overzicht en een kant-en-klaar antwoord zitten erin. Een knop om een
 * regel uit het beveiligingslogboek te verwijderen zit er níet in, en dat
 * is een beslissing die uitleg verdient:
 *
 * 1. **Een logboek waar regels uit te halen zijn, is geen logboek.** Het
 *    bestaat om misbruik te kunnen aantonen en tegenhouden. Zo'n knop is
 *    bovendien precies wat iemand die binnenkomt zou gebruiken om zijn
 *    sporen te wissen -- dan beschermt het logboek niemand meer.
 * 2. **Het hoeft ook niet.** De AVG laat verwijdering weigeren waar de
 *    verwerking nodig is voor een gerechtvaardigd belang, en het
 *    tegengaan van misbruik is dat. Het logboek verdwijnt vanzelf na de
 *    bewaartermijn.
 *
 * Wat er wél kan verdwijnen is een **mislukte mailpoging**, en dat is geen
 * willekeurige uitzondering: dat is de enige plek in deze applicatie waar
 * een bericht van een bezoeker -- naam, adres, inhoud -- in de database
 * kan blijven liggen, en hij was nergens in het portaal te zien.
 *
 * Zie docs/security/verzoeken-van-bezoekers.md.
 */
class LegalController extends Controller
{
    public function index(
        Request $request,
        Gegevensoverzicht $overzicht,
        Antwoordtekst $antwoord,
    ): Response {
        $term = $request->string('zoek')->trim()->toString() ?: null;

        $gebeurtenissen = $overzicht->zoek($term);

        $bewaartermijnBeveiliging = (int) config('security.logging.retention_days');

        return Inertia::render('admin/Juridisch', [
            'zoekterm' => $term,
            'resultaten' => $overzicht->regels($gebeurtenissen),
            'plekken' => $overzicht->plekken(),
            'mislukteMail' => $overzicht->mislukteMail(),

            /*
             * De antwoordtekst komt van de server en staat er in allebei
             * de talen. **De taal van het antwoord hoort bij de bezoeker
             * en niet bij het portaal**: de eigenaar werkt in het
             * Nederlands, maar de vraag kan in het Engels binnenkomen.
             * Stond deze tekst in het component, dan zou hij zijn hele
             * portaal moeten omzetten om één mail te kunnen sturen.
             */
            'antwoord' => $antwoord->voorElkeTaal(
                $term,
                $gebeurtenissen,
                $bewaartermijnBeveiliging,
            ),

            /*
             * De bewaartermijnen die in het antwoord aan een bezoeker
             * terechtkomen. Ze komen van de server zodat de tekst op het
             * scherm niet uit elkaar kan lopen met wat er echt gebeurt.
             */
            'termijnen' => [
                'beveiliging' => $bewaartermijnBeveiliging,
                'mail' => (int) config('mail.log_retention_days'),
                'sessie' => (int) config('session.lifetime'),
                'mislukteMail' => Gegevensoverzicht::MISLUKTE_MAIL_DAGEN,
            ],

            'email' => config('site.email'),
        ]);
    }

    /**
     * Mislukte mailpogingen weggooien.
     *
     * **Wat hier verdwijnt is onherstelbaar en dat staat ook in de
     * bevestiging.** Een mislukte poging is een bericht dat nog verstuurd
     * had kúnnen worden; gooi je hem weg, dan komt hij nooit aan. Dat is
     * soms precies wat je wil -- iemand vraagt om verwijdering -- en soms
     * het laatste wat je wil, namelijk als het bericht alleen is blijven
     * steken omdat de mailprovider eruit lag.
     *
     * Het gaat in het beveiligingslogboek, want het is een handeling van
     * de beheerder waar gegevens door verdwijnen. Zonder die regel is er
     * later geen manier om te zien dat het is gebeurd.
     */
    public function destroyFailedMail(SecurityLogger $logboek): RedirectResponse
    {
        $aantal = DB::table('failed_jobs')->count();

        if ($aantal === 0) {
            Toast::melding(__('Er stonden geen mislukte mailpogingen klaar.'));

            return back();
        }

        DB::table('failed_jobs')->delete();

        $logboek->success(
            SecurityEventType::PrivacyDataCleared,
            user: request()->user(),
            context: ['aantal' => $aantal],
        );

        Toast::verwijderd(__(':aantal mislukte mailpogingen zijn verwijderd.', [
            'aantal' => $aantal,
        ]));

        return back();
    }
}
