<?php

namespace App\Console\Commands;

use App\Models\MailLog;
use App\Support\Maintenance\RowPruner;
use Illuminate\Console\Command;

/**
 * Ruimt het mailoverzicht op volgens de bewaartermijn.
 *
 * In `mail_logs` staat alleen metadata, geen mailinhoud, maar de tabel groeit
 * wel met elke verstuurde mail én met elke statusmelding van de provider.
 *
 * De termijn staat los van die van het beveiligingslogboek: een bounce van
 * een jaar geleden zegt niets meer, een inlogpoging van een jaar geleden
 * mogelijk wel.
 *
 * Zie docs/operations/onderhoudstaken.md.
 */
class PruneMailLogs extends Command
{
    protected $signature = 'mail:prune-logs
                            {--days= : Wijk eenmalig af van de ingestelde bewaartermijn}';

    protected $description = 'Verwijder regels uit het mailoverzicht die ouder zijn dan de bewaartermijn';

    public function handle(RowPruner $pruner): int
    {
        $option = $this->option('days');
        $days = is_numeric($option)
            ? (int) $option
            : (int) config('mail.log_retention_days');

        if ($days < 1) {
            $this->error(__('De bewaartermijn moet minstens één dag zijn.'));

            return self::FAILURE;
        }

        $before = now()->subDays($days);
        $deleted = $pruner->prune(MailLog::query(), $before);

        $this->info(__(':aantal regels uit het mailoverzicht verwijderd (ouder dan :datum).', [
            'aantal' => $deleted,
            'datum' => $before->toDateString(),
        ]));

        return self::SUCCESS;
    }
}
