<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Datum;
use Database\Factories\EducationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eén opleiding, onder de certificaten.
 *
 * **Bewust een stuk kaler dan een certificaat.** Geen logo, geen
 * detailvenster, geen certificaatnummer, en geen volgorde die de klant
 * zelf bepaalt. Dit is een lijstje van twee of drie regels; alles wat je
 * er verder omheen bouwt is gereedschap voor een probleem dat niet
 * bestaat.
 *
 * De terugval tussen de talen volgt dezelfde regel als overal: de
 * **naam** valt terug op het Nederlands -- een regel zonder opleiding is
 * geen regel -- en het **niveau** niet. De instelling wordt niet
 * vertaald, want dat is een eigennaam.
 *
 * Zie docs/architecture/modules/certificaten.md.
 *
 * @property int $id
 * @property bool $published
 * @property string $title_nl
 * @property string|null $title_en
 * @property string $institution
 * @property string|null $level_nl
 * @property string|null $level_en
 * @property Carbon $started_on
 * @property Carbon|null $ended_on
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'published',
    'title_nl',
    'title_en',
    'institution',
    'level_nl',
    'level_en',
    'started_on',
    'ended_on',
    'machine_translated_at',
])]
class Education extends Model
{
    /** @use HasFactory<EducationFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * De tabelnaam staat er expliciet bij, en dat moet.
     *
     * Laravel leidt hem normaal af uit de klassenaam, maar "education"
     * is in het Engels een niet-telbaar woord: de meervoudsvormer laat
     * hem staan en zoekt dus naar een tabel `education`. De migratie
     * maakt `educations`, want een tabel in het enkelvoud tussen
     * `certificates` en `services` leest als een fout.
     */
    protected $table = 'educations';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'started_on' => 'date',
            'ended_on' => 'date',
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
     * Wat nog loopt bovenaan, daarna op periode van nieuw naar oud.
     *
     * Dezelfde volgorde als de tijdlijn, en om dezelfde reden niet
     * instelbaar: een opleidingslijst die niet op volgorde staat is
     * gewoon fout, en een sleepgreep nodigt uit tot die fout.
     *
     * `id` als laatste sleutel is geen overbodige zorgvuldigheid: zonder
     * die regel is de volgorde van twee opleidingen die in dezelfde
     * maand eindigden aan de database, en die mag daar per keer anders
     * over denken.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpPeriode(Builder $query): Builder
    {
        return $query
            ->orderByRaw('(ended_on is null) desc')
            ->orderByDesc('ended_on')
            ->orderByDesc('started_on')
            ->orderByDesc('id');
    }

    /** De naam van de opleiding, met terugval op het Nederlands. */
    public function naam(): string
    {
        return $this->engels() && filled($this->title_en)
            ? (string) $this->title_en
            : $this->title_nl;
    }

    /** Het niveau, of null. Optioneel, dus zonder terugval. */
    public function niveau(): ?string
    {
        $tekst = $this->engels() ? $this->level_en : $this->level_nl;

        return filled($tekst) ? (string) $tekst : null;
    }

    /**
     * "2018 — 2022", of "2022 — heden" als hij nog loopt.
     *
     * Alleen jaartallen en geen maanden, anders dan bij een certificaat.
     * Een opleiding duurt jaren; de maand erbij zetten maakt de regel
     * langer zonder dat iemand er iets aan heeft.
     */
    public function periode(): string
    {
        $begin = $this->started_on->format('Y');
        $eind = $this->ended_on?->format('Y') ?? __('heden');

        return $begin === $eind ? $begin : "{$begin} — {$eind}";
    }

    /** De einddatum als maand, voor het beheerscherm. */
    public function afgerond(): ?string
    {
        return Datum::maand($this->ended_on);
    }

    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }

    public static function activityName(): string
    {
        return __('Opleiding');
    }

    public function activityLabel(): string
    {
        return "{$this->title_nl} ({$this->institution})";
    }
}
