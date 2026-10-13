<?php

namespace App\Models;

use App\Enums\StatisticDisplay;
use App\Models\Concerns\LogsActivity;
use Database\Factories\StatisticFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eén statistiek op de landingspagina.
 *
 * Een vaardigheid met een niveau, of een kengetal. Wélke vorm hij op de
 * site krijgt -- balk, ring of teller -- kiest de klant zelf per item;
 * zie StatisticDisplay.
 *
 * **De terugval tussen de talen is hier beslist en niet in Vue.** De
 * regel is overal dezelfde:
 *
 * | Veld     | Engels leeg                                          |
 * | -------- | ---------------------------------------------------- |
 * | Label    | Terugvallen -- een cijfer zonder naam is een cijfer zonder betekenis. |
 * | Notitie  | Weglaten -- de tegel ziet er zonder ook goed uit.    |
 * | Groep    | Terugvallen op het Nederlands; zie `groep()`.        |
 *
 * Zie docs/architecture/modules/statistieken.md.
 *
 * @property int $id
 * @property StatisticDisplay $display
 * @property bool $published
 * @property int $position
 * @property string $label_nl
 * @property string|null $label_en
 * @property int $value
 * @property string|null $prefix
 * @property string|null $suffix
 * @property string|null $note_nl
 * @property string|null $note_en
 * @property string|null $group_nl
 * @property string|null $group_en
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'display',
    'published',
    'position',
    'label_nl',
    'label_en',
    'value',
    'prefix',
    'suffix',
    'note_nl',
    'note_en',
    'group_nl',
    'group_en',
    'machine_translated_at',
])]
class Statistic extends Model
{
    /** @use HasFactory<StatisticFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'display' => StatisticDisplay::class,
            'published' => 'boolean',
            'position' => 'integer',
            'value' => 'integer',
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
        // De tweede sortering is geen overdaad: twee statistieken met
        // dezelfde positie zouden anders per query van plek wisselen.
        return $query->orderBy('position')->orderBy('id');
    }

    /** Het label, met terugval op het Nederlands. */
    public function naam(): string
    {
        return $this->engels() && filled($this->label_en)
            ? (string) $this->label_en
            : $this->label_nl;
    }

    /** Het regeltje eronder, of null. Optioneel, dus zonder terugval. */
    public function notitie(): ?string
    {
        $tekst = $this->engels() ? $this->note_en : $this->note_nl;

        return filled($tekst) ? (string) $tekst : null;
    }

    /**
     * De **sleutel** waarop gegroepeerd wordt.
     *
     * Altijd het Nederlandse veld, ook voor een Engelse bezoeker, en een
     * lege groep is een lege string. Zou je op de vertaalde waarde
     * groeperen, dan valt een groep in het Engels uit elkaar zodra één
     * item zijn Engelse groepsnaam mist -- en dan staat dezelfde site in
     * twee talen anders ingedeeld.
     */
    public function groepSleutel(): string
    {
        return (string) ($this->group_nl ?? '');
    }

    /**
     * Het **kopje** boven de groep, in de taal van de bezoeker.
     *
     * Hier valt het Engels wél terug op het Nederlands: een groep zonder
     * kop is een streep zonder uitleg. Dit is dus puur opmaak; de
     * indeling zelf hangt aan `groepSleutel()`.
     */
    public function groep(): ?string
    {
        if (blank($this->group_nl)) {
            return null;
        }

        return $this->engels() && filled($this->group_en)
            ? (string) $this->group_en
            : (string) $this->group_nl;
    }

    /**
     * De groepen die er al zijn, voor de suggesties in het formulier.
     *
     * Zonder die lijst belanden "netwerk" en "Netwerk" naast elkaar als
     * twee groepen, en dan valt het blok uit elkaar zonder dat iemand
     * ziet waarom.
     *
     * @return array<int, string>
     */
    public static function bestaandeGroepen(): array
    {
        return self::query()
            ->whereNotNull('group_nl')
            ->where('group_nl', '!=', '')
            ->distinct()
            ->orderBy('group_nl')
            ->pluck('group_nl')
            ->all();
    }

    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }

    public static function activityName(): string
    {
        return __('Statistiek');
    }

    public function activityLabel(): string
    {
        return $this->label_nl;
    }
}
