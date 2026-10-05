<?php

namespace App\Models;

use App\Enums\ContactVeld;
use App\Enums\ContactVeldStatus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * De stand van één veld op het contactformulier.
 *
 * Deze rij bewaart alleen of het veld er staat, of het moet en waar het
 * staat. Wát een veld is -- het invoertype, de validatieregel, het label --
 * staat in [`ContactVeld`](../Enums/ContactVeld.php).
 *
 * Zie docs/architecture/modules/contact.md.
 *
 * @property int $id
 * @property ContactVeld $key
 * @property ContactVeldStatus $status
 * @property int $position
 * @property bool $allow_custom
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'key',
    'status',
    'position',
    'allow_custom',
])]
class ContactField extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => ContactVeld::class,
            'status' => ContactVeldStatus::class,
            'position' => 'integer',
            'allow_custom' => 'boolean',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpVolgorde(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * De stand, met de vaste velden afgedwongen.
     *
     * **Lees nooit rechtstreeks `$veld->status`.** Een vast veld staat in
     * de database op "verplicht", maar als die rij met de hand of via een
     * aangepast verzoek wordt omgezet, hoort het formulier dat te negeren
     * in plaats van open te gaan staan. De waarheid staat in
     * `ContactVeld::vast()` en dus in de code.
     */
    public function stand(): ContactVeldStatus
    {
        return $this->key->vast()
            ? ContactVeldStatus::Verplicht
            : $this->status;
    }

    /**
     * Of de bezoeker hier een eigen antwoord mag typen.
     *
     * Alleen van betekenis op het onderwerp. Bij elk ander veld is het
     * altijd `false`, want "typ zelf een naam" bestaat niet.
     */
    public function eigenToegestaan(): bool
    {
        return $this->key === ContactVeld::Onderwerp && $this->allow_custom;
    }

    public static function activityName(): string
    {
        return __('Veld van het contactformulier');
    }

    public function activityLabel(): string
    {
        return $this->key->sleutel();
    }
}
