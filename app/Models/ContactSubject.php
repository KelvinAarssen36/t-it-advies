<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\ContactSubjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eén onderwerp waaruit een bezoeker kan kiezen op het contactformulier.
 *
 * Hetzelfde patroon als elke andere module: tweetalig, aan en uit te
 * zetten, versleepbaar. Het Engelse label mag leeg blijven en valt dan
 * terug op het Nederlands -- een keuzelijst met een gat erin is geen
 * keuzelijst.
 *
 * **Hard verwijderen mag.** Er zijn geen soft deletes, want `published`
 * lost hetzelfde al op en een tweede toestand ernaast maakt het alleen
 * verwarrend. Oude aanvragen blijven leesbaar omdat die de onderwerptekst
 * zelf bewaren; zie ContactSubmission.
 *
 * Zie docs/architecture/modules/contact.md.
 *
 * @property int $id
 * @property string $label_nl
 * @property string|null $label_en
 * @property bool $published
 * @property bool $featured
 * @property int $position
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'label_nl',
    'label_en',
    'published',
    'featured',
    'position',
    'machine_translated_at',
])]
class ContactSubject extends Model
{
    /** @use HasFactory<ContactSubjectFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'featured' => 'boolean',
            'position' => 'integer',
            'machine_translated_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpVolgorde(Builder $query): Builder
    {
        // De tweede sortering is geen overdaad: twee onderwerpen met
        // dezelfde positie zouden anders per query van plek wisselen.
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * Het label, met terugval op het Nederlands.
     *
     * **Zonder taal is het die van nu**, en dat is bijna altijd goed: op de
     * site de taal van de bezoeker, in het portaal die van de eigenaar.
     *
     * Eén aanroeper geeft hem wél mee, en die heeft een goede reden: de
     * melding aan de eigenaar wordt opgebouwd tijdens het verzoek van de
     * bezoeker, dus daar staat de taal nog op de zijne. Zie
     * ContactController::verstuur().
     */
    public function naam(?string $taal = null): string
    {
        $taal ??= app()->getLocale();

        return $taal === 'en' && filled($this->label_en)
            ? (string) $this->label_en
            : $this->label_nl;
    }

    /** Hoeveel aanvragen aan dit onderwerp hangen. */
    public function aanvragen(): int
    {
        return ContactSubmission::query()->where('subject_id', $this->id)->count();
    }

    public static function activityName(): string
    {
        return __('Contactonderwerp');
    }

    public function activityLabel(): string
    {
        return $this->label_nl;
    }
}
