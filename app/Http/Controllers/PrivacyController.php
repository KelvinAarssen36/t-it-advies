<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

/**
 * De privacyverklaring op de publieke site.
 *
 * **De tekst staat in de frontend en niet in de database**, en dat is een
 * bewuste keuze die uitleg verdient. Een privacyverklaring beschrijft wat
 * de software doet. Zou de eigenaar hem zelf kunnen aanpassen, dan kan hij
 * een verklaring neerzetten die niet meer klopt bij de werkelijkheid -- en
 * een verklaring die niet klopt is erger dan geen verklaring. Nu verandert
 * hij mee in dezelfde wijziging als de meting zelf.
 *
 * Wat hier wél van de server komt is het e-mailadres. Dat staat in
 * `config/site.php` als enige bron, zodat het adres in de verklaring niet
 * uit elkaar kan lopen met het adres waar het contactformulier naartoe
 * gaat. Zie docs/architecture/mail-en-queues.md over die twee adressen.
 *
 * Zie docs/architecture/bezoekcijfers.md.
 */
class PrivacyController extends Controller
{
    public function __invoke(): Response
    {
        // `public/` en niet de hoofdmap: app.ts geeft elke pagina daar
        // vanzelf de layout van de publieke site. Zie de switch daar.
        return Inertia::render('public/Privacy', [
            'email' => config('site.email'),

            /*
             * De bewaartermijn van het beveiligingslogboek komt uit de
             * instelling die hem ook echt bepaalt. Een getal dat hier met
             * de hand staat, klopt tot de dag dat iemand die instelling
             * wijzigt -- en dan staat er een onwaarheid in een juridische
             * tekst.
             */
            'bewaartermijnBeveiliging' => (int) config('security.logging.retention_days'),

            /*
             * Hoe lang het mailoverzicht een verstuurd bericht bijhoudt.
             * Ook dat hoort in de verklaring, want het gaat over een
             * bericht dat een bezoeker heeft gestuurd.
             */
            'bewaartermijnMail' => (int) config('mail.log_retention_days'),

            /*
             * Of de spamcontrole van Cloudflare daadwerkelijk aanstaat.
             *
             * **Dit is geen schakelaar voor de vormgeving maar voor de
             * waarheid.** Staat er geen sleutel, dan wordt er geen enkel
             * verzoek naar Cloudflare gedaan en zou een alinea over
             * Cloudflare in de verklaring een onwaarheid zijn -- een derde
             * partij noemen die er niet is, is net zo fout als er een
             * verzwijgen. Zet de eigenaar hem later aan, dan verschijnt de
             * alinea vanzelf.
             */
            'spamcontrole' => filled(config('services.turnstile.site_key')),

            /*
             * Hoe lang een sessie meegaat, in minuten.
             *
             * **Dit hoort in de verklaring en het stond er eerst niet.**
             * `SESSION_DRIVER` staat op `database`, en de sessiedriver van
             * Laravel schrijft bij elke bezoeker een rij met zijn
             * IP-adres en browserkenmerk. Dat is niet erg -- het is nodig
             * voor de taalkeuze en de beveiliging van het formulier -- maar
             * het is wél een gegeven van een bezoeker dat op onze server
             * staat, en dan hoort het erbij te staan.
             *
             * Het viel pas op bij het inventariseren van álle tabellen
             * waar een IP-adres in kan staan. Zie
             * docs/architecture/bezoekcijfers.md.
             */
            'sessieMinuten' => (int) config('session.lifetime'),
        ]);
    }
}
