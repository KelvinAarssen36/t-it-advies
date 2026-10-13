<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Eén regel uit het activiteitenlogboek: wie heeft wat veranderd.
 *
 * Rijen zijn append-only. Ze worden nooit bijgewerkt, alleen opgeruimd door
 * het retentiebeleid, en daarom heeft de tabel geen updated_at. Een
 * logboek dat je kunt wijzigen is geen logboek.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $actor_name
 * @property ActivityAction $action
 * @property string $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_label
 * @property array<string, array{van: mixed, naar: mixed}>|null $changes
 * @property string|null $ip_address
 * @property Carbon $created_at
 * @property-read User|null $user
 */
#[Fillable([
    'user_id',
    'actor_name',
    'action',
    'subject_type',
    'subject_id',
    'subject_label',
    'changes',
    'ip_address',
])]
class ActivityEntry extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'changes' => 'array',
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
     * Hoe het onderdeel heet waar dit over gaat.
     *
     * De klasse mag dat zelf zeggen via `activityName()`; dat is de nette
     * naam die de klant ziet ("Dienst" in plaats van "Service"). Bestaat de
     * klasse niet meer -- bijvoorbeeld omdat een module is weggehaald -- dan
     * valt hij terug op de losse klassenaam, zodat oude regels leesbaar
     * blijven in plaats van te verdwijnen.
     */
    public function subjectName(): string
    {
        $class = $this->subject_type;

        if (class_exists($class) && method_exists($class, 'activityName')) {
            return $class::activityName();
        }

        return Str::headline(class_basename($class));
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOfAction(Builder $query, ActivityAction|string $action): Builder
    {
        return $query->where('action', $action instanceof ActivityAction ? $action->value : $action);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeForSubject(Builder $query, Model $subject): Builder
    {
        return $query
            ->where('subject_type', $subject::class)
            ->where('subject_id', $subject->getKey());
    }
}
