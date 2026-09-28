<?php

namespace App\Console\Commands;

use App\Models\ActivityEntry;
use App\Support\Datum;
use App\Support\Maintenance\RowPruner;
use Illuminate\Console\Command;

/**
 * Ruimt het activiteitenlogboek op volgens de bewaartermijn.
 *
 * De termijn staat los van die van het beveiligingslogboek, en dat is geen
 * toeval. Bij onderzoek naar een inbraak wil je maanden terug kunnen kijken;
 * bij "wat heb ik vorige maand aan die pagina veranderd" is een jaar ruim
 * voldoende en daarna is het ballast.
 *
 * Zonder deze taak groeit de tabel onbeperkt door, en dan worden juist de
 * zoekopdrachten traag die je nodig hebt op het moment dat er iets ontbreekt
 * op de site.
 *
 * Zie docs/operations/onderhoudstaken.md.
 */
class PruneActivityEntries extends Command
{
    protected $signature = 'activity:prune
                            {--days= : Wijk eenmalig af van de ingestelde bewaartermijn}';

    protected $description = 'Verwijder regels uit het activiteitenlogboek die ouder zijn dan de bewaartermijn';

    public function handle(RowPruner $pruner): int
    {
        $option = $this->option('days');
        $days = is_numeric($option)
            ? (int) $option
            : (int) config('security.logging.activity_retention_days');

        // Nul dagen zou het hele logboek wissen. Dat is nooit de bedoeling
        // van een onderhoudstaak, dus weigeren we het.
        if ($days < 1) {
            $this->error(__('De bewaartermijn moet minstens één dag zijn.'));

            return self::FAILURE;
        }

        $before = now()->subDays($days);
        $deleted = $pruner->prune(ActivityEntry::query(), $before);

        $this->info(__(':aantal regels uit het activiteitenlogboek verwijderd (ouder dan :datum).', [
            'aantal' => $deleted,
            'datum' => Datum::dag($before),
        ]));

        return self::SUCCESS;
    }
}
