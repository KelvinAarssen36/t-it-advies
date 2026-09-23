<?php

namespace App\Console\Commands;

use App\Models\SecurityEvent;
use App\Support\Maintenance\RowPruner;
use Illuminate\Console\Command;

/**
 * Ruimt het beveiligingslogboek op volgens de bewaartermijn.
 *
 * De tabel krijgt een rij bij elke inlogpoging, elke geblokkeerde bot en elke
 * geweigerde webhook. Zonder deze taak groeit hij onbeperkt door, en dan
 * worden juist de zoekopdrachten traag die je nodig hebt op het moment dat er
 * iets aan de hand is.
 *
 * De termijn is bewust lang (standaard een jaar): bij onderzoek naar een
 * inbraak wil je maanden terug kunnen kijken. Het losse logbestand
 * `storage/logs/security.log` heeft zijn eigen termijn en wordt door Laravel
 * zelf geroteerd.
 *
 * Zie docs/operations/onderhoudstaken.md.
 */
class PruneSecurityEvents extends Command
{
    protected $signature = 'security:prune-events
                            {--days= : Wijk eenmalig af van de ingestelde bewaartermijn}';

    protected $description = 'Verwijder beveiligingsgebeurtenissen die ouder zijn dan de bewaartermijn';

    public function handle(RowPruner $pruner): int
    {
        $option = $this->option('days');
        $days = is_numeric($option)
            ? (int) $option
            : (int) config('security.logging.retention_days');

        // Nul dagen zou het hele logboek wissen. Dat is nooit de bedoeling van
        // een onderhoudstaak, dus weigeren we het in plaats van het uit te voeren.
        if ($days < 1) {
            $this->error(__('De bewaartermijn moet minstens één dag zijn.'));

            return self::FAILURE;
        }

        $before = now()->subDays($days);
        $deleted = $pruner->prune(SecurityEvent::query(), $before);

        $this->info(__(':aantal regels uit het beveiligingslogboek verwijderd (ouder dan :datum).', [
            'aantal' => $deleted,
            'datum' => $before->toDateString(),
        ]));

        return self::SUCCESS;
    }
}
