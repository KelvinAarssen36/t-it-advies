<?php

namespace App\Models;

use App\Enums\ServiceIcon;
use App\Models\Concerns\LogsActivity;
use Database\Factories\ServiceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Eén dienst op de landingspagina.
 *
 * **Drie soorten tekst, elk met een eigen plek op het scherm.** De
 * `title` en de `summary` staan op de kaart; de `body` staat in het
 * venster dat opengaat als je erop klikt. Is de body leeg, dan is de
 * kaart niet aanklikbaar -- zo bepaalt de klant per dienst of er meer te
 * vertellen valt.
 *
 * De **expertisepunten** hangen eronder als eigen rijen; zie ServicePoint.
 *
 * **De terugval tussen de talen is hier beslist en niet in Vue.** De
 * regel is overal dezelfde: een verplicht veld valt terug op het
 * Nederlands, een optioneel veld wordt weggelaten. Dus `titel()` en
 * `samenvatting()` vallen terug -- een kaart zonder kop of zonder tekst
 * is stuk -- en `verhaal()` niet. Zou de Vue-component dat zelf moeten
 * bedenken, dan staat er vroeg of laat half Nederlands op een Engelse
 * pagina.
 *
 * Zie docs/architecture/modules/diensten.md.
 *
 * @property int $id
 * @property ServiceIcon $icon
 * @property bool $published
 * @property int $position
 * @property string $title_nl
 * @property string|null $title_en
 * @property string $summary_nl
 * @property string|null $summary_en
 * @property string|null $body_nl
 * @property string|null $body_en
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Collection<int, ServicePoint> $points
 */
#[Fillable([
    'icon',
    'published',
    'position',
    'title_nl',
    'title_en',
    'summary_nl',
    'summary_en',
    'body_nl',
    'body_en',
    'machine_translated_at',
])]
class Service extends Model
{
    /** @use HasFactory<ServiceFactory> */
    use HasFactory;

    use LogsActivity;

    /** Meer punten dan dit lopen de kaarten in het raster uit elkaar. */
    public const PUNTEN_MAXIMUM = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'icon' => ServiceIcon::class,
            'published' => 'boolean',
            'position' => 'integer',
            'machine_translated_at' => 'datetime',
        ];
    }

    /**
     * De expertisepunten, altijd op volgorde.
     *
     * De sortering staat op de relatie en niet bij elke aanroep: een
     * lijstje dat de ene keer anders staat dan de andere is precies het
     * soort verschil dat je pas op de website ziet.
     *
     * @return HasMany<ServicePoint, $this>
     */
    public function points(): HasMany
    {
        return $this->hasMany(ServicePoint::class)
            ->orderBy('position')
            ->orderBy('id');
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
        // De tweede sortering is geen overdaad: twee diensten met
        // dezelfde positie zouden anders per query van plek wisselen.
        return $query->orderBy('position')->orderBy('id');
    }

    /** De titel, met terugval op het Nederlands. */
    public function titel(): string
    {
        return $this->engels() && filled($this->title_en)
            ? (string) $this->title_en
            : $this->title_nl;
    }

    /** De tekst op de kaart, met terugval op het Nederlands. */
    public function samenvatting(): string
    {
        return $this->engels() && filled($this->summary_en)
            ? (string) $this->summary_en
            : $this->summary_nl;
    }

    /** Het hele verhaal, of null. Optioneel, dus zonder terugval. */
    public function verhaal(): ?string
    {
        $tekst = $this->engels() ? $this->body_en : $this->body_nl;

        return filled($tekst) ? (string) $tekst : null;
    }

    /**
     * De expertisepunten in de taal van de bezoeker.
     *
     * Een punt zonder tekst in die taal valt weg en niet terug. Een
     * lijstje met drie Engelse en twee Nederlandse labels is slordiger
     * dan een lijstje van drie.
     *
     * @return array<int, string>
     */
    public function punten(): array
    {
        return $this->points
            ->map(fn (ServicePoint $punt) => $punt->tekst())
            ->filter()
            ->values()
            ->all();
    }

    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }

    public static function activityName(): string
    {
        return __('Dienst');
    }

    public function activityLabel(): string
    {
        return $this->title_nl;
    }
}
