<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Eén expertisepunt onder een dienst.
 *
 * Een kort label -- "monitoring", "back-ups" -- dat op de website als
 * klein blokje onder de tekst van de kaart staat.
 *
 * **Geen eigen activiteitenlogboek.** Een punt bestaat niet los van zijn
 * dienst, en de eigenaar bewerkt ze ook in hetzelfde venster. Zou elk
 * punt een eigen regel krijgen, dan levert één keer opslaan er zes op en
 * is het logboek niet meer te lezen. Wat er met de dienst gebeurt staat
 * er wél in; zie Service.
 *
 * Zie docs/architecture/modules/diensten.md.
 *
 * @property int $id
 * @property int $service_id
 * @property int $position
 * @property string $text_nl
 * @property string|null $text_en
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['service_id', 'position', 'text_nl', 'text_en'])]
class ServicePoint extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Service, $this>
     */
    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /**
     * De tekst in de taal van de bezoeker, of null.
     *
     * Zonder terugval, anders dan bij de titel van een dienst. Een
     * lijstje waarin twee van de vijf labels ineens Nederlands zijn
     * leest slechter dan een lijstje van drie.
     */
    public function tekst(): ?string
    {
        $tekst = app()->getLocale() === 'en' ? $this->text_en : $this->text_nl;

        return filled($tekst) ? (string) $tekst : null;
    }
}
