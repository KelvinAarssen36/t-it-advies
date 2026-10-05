<?php

namespace App\Console\Commands;

use App\Models\ContactSubmission;
use App\Support\Datum;
use App\Support\Maintenance\RowPruner;
use Illuminate\Console\Command;

/**
 * Ruimt binnengekomen contactaanvragen op volgens de bewaartermijn.
 *
 * **Dit is de enige opruimtaak die over inhoud van een bezoeker gaat** en
 * niet over een logboek. In een aanvraag staan een naam, een e-mailadres
 * en een bericht; die horen niet langer te blijven staan dan nodig.
 *
 * De termijn staat in `config('site.contact.retention_days')` en is
 * **dezelfde waarde die in de privacyverklaring aan de bezoeker wordt
 * beloofd**. Verander je hem, dan verandert die tekst mee -- dat is
 * geregeld doordat allebei uit deze instelling lezen en niet uit een
 * getypte zin.
 *
 * Zie docs/operations/onderhoudstaken.md en
 * docs/architecture/modules/contact.md.
 */
class PruneContactSubmissions extends Command
{
    protected $signature = 'contact:prune
                            {--days= : Wijk eenmalig af van de ingestelde bewaartermijn}';

    protected $description = 'Verwijder contactaanvragen die ouder zijn dan de bewaartermijn';

    public function handle(RowPruner $pruner): int
    {
        $option = $this->option('days');
        $days = is_numeric($option)
            ? (int) $option
            : (int) config('site.contact.retention_days');

        if ($days < 1) {
            $this->error(__('De bewaartermijn moet minstens één dag zijn.'));

            return self::FAILURE;
        }

        $before = now()->subDays($days);
        $deleted = $pruner->prune(ContactSubmission::query(), $before);

        $this->info(__(':aantal contactaanvragen verwijderd (ouder dan :datum).', [
            'aantal' => $deleted,
            'datum' => Datum::dag($before),
        ]));

        return self::SUCCESS;
    }
}
