<?php

namespace App\Support\Maintenance;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Verwijdert oude rijen in blokken.
 *
 * Twee redenen om dit niet met één grote DELETE te doen. Op MySQL houdt die
 * de tabel lang op slot terwijl de applicatie er tegelijk in schrijft --
 * `security_events` krijgt bij elke inlogpoging een rij. En `DELETE ... LIMIT`
 * bestaat niet op SQLite, waar de tests op draaien; daarom halen we eerst de
 * sleutels op en verwijderen we daarna op sleutel.
 *
 * Dit is een mass delete: modelevents draaien niet. Voor logboektabellen is
 * dat precies wat je wilt, want die hangen nergens aan vast.
 *
 * Zie docs/operations/onderhoudstaken.md.
 */
class RowPruner
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query  de tabel, eventueel al gefilterd
     * @param  CarbonInterface  $before  alles van vóór dit moment gaat weg
     * @return int het aantal verwijderde rijen
     */
    public function prune(
        Builder $query,
        CarbonInterface $before,
        string $column = 'created_at',
        int $chunk = 1000,
    ): int {
        $key = $query->getModel()->getKeyName();
        $deleted = 0;

        do {
            $keys = (clone $query)
                ->where($column, '<', $before)
                ->limit($chunk)
                ->pluck($key);

            if ($keys->isEmpty()) {
                break;
            }

            $deleted += (int) (clone $query)->whereIn($key, $keys)->delete();
        } while ($keys->count() === $chunk);

        return $deleted;
    }
}
