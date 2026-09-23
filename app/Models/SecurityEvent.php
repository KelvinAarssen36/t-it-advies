<?php

namespace App\Models;

use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Een vastgelegde beveiligingsgebeurtenis.
 *
 * Rijen zijn append-only: ze worden nooit bijgewerkt, alleen opgeruimd door
 * het retentiebeleid. Daarom heeft de tabel geen updated_at.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string|null $email
 * @property string $event
 * @property SecurityOutcome $outcome
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property array<string, mixed>|null $context
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[Fillable(['user_id', 'email', 'event', 'outcome', 'ip_address', 'user_agent', 'context'])]
class SecurityEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => SecurityOutcome::class,
            'context' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Het menselijke label van de gebeurtenis, met de ruwe waarde als
     * terugvaloptie zodat een onbekend type de weergave niet breekt.
     */
    public function label(): string
    {
        return SecurityEventType::tryFrom($this->event)?->label() ?? $this->event;
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeFailures(Builder $query): Builder
    {
        return $query->where('outcome', SecurityOutcome::Failure);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOfType(Builder $query, SecurityEventType|string $type): Builder
    {
        return $query->where('event', $type instanceof SecurityEventType ? $type->value : $type);
    }
}
