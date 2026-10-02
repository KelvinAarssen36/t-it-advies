<?php

namespace App\Console\Commands;

use App\Support\Bezoek\Bezoekteller;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Gooit de bezoekerscodes van voorbije dagen weg.
 *
 * **Dit is een tweede slot en niet het eerste.** Het opruimen gebeurt
 * normaal al bij het eerste bezoek na middernacht, in dezelfde handeling
 * waarin er een nieuw dagzout komt -- zie Bezoekteller. Dat is met opzet zo
 * gebouwd: een belofte aan de bezoeker die afhangt van een cronregel die
 * iemand vergeet, is geen belofte.
 *
 * Deze taak is er voor het geval er dagen niemand op de site komt. Dan
 * blijven de codes van de laatste bezoeker staan tot de volgende langskomt,
 * en dat kan een week duren. Met deze taak is het de volgende nacht weg.
 *
 * Anders dan de andere opruimtaken heeft hij **geen instelbare
 * bewaartermijn**. Die is één dag en dat is het hele punt; een knop om er
 * dertig dagen van te maken is een knop om de belofte te breken.
 *
 * Zie docs/architecture/bezoekcijfers.md en
 * docs/operations/onderhoudstaken.md.
 */
class PruneVisitorCodes extends Command
{
    protected $signature = 'bezoek:prune-codes';

    protected $description = 'Verwijder de bezoekerscodes en het zout van voorbije dagen';

    public function handle(Bezoekteller $teller): int
    {
        $vandaag = $teller->vandaag();

        $codes = DB::table('site_visitor_codes')->where('day', '<', $vandaag)->delete();

        /*
         * Het zout gaat in dezelfde beweging mee. Zonder dat blijft het
         * geheim van gisteren liggen terwijl de codes waar het bij hoorde
         * er niet meer zijn -- nutteloos, en precies het soort restje dat
         * je niet wil bewaren.
         */
        DB::table('site_visitor_salts')->where('day', '<', $vandaag)->delete();

        $this->info(__(':aantal bezoekerscodes van voorbije dagen verwijderd.', [
            'aantal' => $codes,
        ]));

        return self::SUCCESS;
    }
}
