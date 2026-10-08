<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\WorkStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eén stap van de werkwijze.
 *
 * **Het nummer staat niet in de database.** Dat volgt uit `position`, en
 * dat is met opzet: het nummer ís de volgorde. Zou de eigenaar het apart
 * kunnen invullen, dan krijg je vroeg of laat een lijst die begint bij 01,
 * 03, 02 -- en dan betekent het niets meer.
 *
 * **De terugval tussen de talen is hier beslist en niet in Vue**, dezelfde
 * regel als bij `Service` en `Project`: een verplicht veld valt terug op
 * het Nederlands, een optioneel veld wordt weggelaten.
 *
 * Alleen `titel()` valt dus terug. `samenvatting()` niet, en dat is een
 * bewust verschil met `Service::samenvatting()`: daar staat de kaart leeg
 * zonder tekst, hier blijft er een genummerde stap met een titel over en
 * dat leest nog steeds als een werkwijze. En de duur al helemaal niet --
 * "1-2 weken" is geen naam maar een zin met een Nederlands woord erin.
 *
 * Zie docs/architecture/modules/werkwijze.md.
 *
 * @property int $id
 * @property int $position
 * @property bool $published
 * @property string $title_nl
 * @property string|null $title_en
 * @property string $summary_nl
 * @property string|null $summary_en
 * @property string|null $duration_nl
 * @property string|null $duration_en
 * @property string|null $result_nl
 * @property string|null $result_en
 * @property string|null $body_nl
 * @property string|null $body_en
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'position',
    'published',
    'title_nl',
    'title_en',
    'summary_nl',
    'summary_en',
    'duration_nl',
    'duration_en',
    'result_nl',
    'result_en',
    'body_nl',
    'body_en',
    'machine_translated_at',
])]
class WorkStep extends Model
{
    /** @use HasFactory<WorkStepFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * Hoeveel stappen een werkwijze er hoogstens heeft.
     *
     * **Dit is geen technische grens maar een inhoudelijke.** Een
     * werkwijze is geen catalogus maar een verhaal dat een bezoeker moet
     * kunnen onthouden; drie tot zes stappen onthoud je, negen is geen
     * werkwijze meer maar een projectplan. Wie een zevende nodig heeft kan
     * er bijna altijd beter twee samenvoegen, en dat zegt het scherm er
     * ook bij.
     *
     * Praktisch telt het ook: vanaf vijf stappen staat het blok als kolom,
     * en die is zo hoog als de som van alle stappen. Bij zes is dat zo'n
     * 550 beeldpunten -- nog te overzien. Bij tien niet meer.
     *
     * Zelfde soort afspraak als `Service::PUNTEN_MAXIMUM`.
     */
    public const MAXIMUM = 6;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published' => 'boolean',
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
        // De tweede sortering is geen overdaad: twee stappen met dezelfde
        // positie zouden anders per query van plek wisselen -- en hier
        // bepaalt die plek ook nog eens het nummer op de kaart.
        return $query->orderBy('position')->orderBy('id');
    }

    /** De titel, met terugval op het Nederlands. */
    public function titel(): string
    {
        return $this->engels() && filled($this->title_en)
            ? (string) $this->title_en
            : $this->title_nl;
    }

    /** De zin onder de titel, of null. Zonder terugval; zie het blok boven. */
    public function samenvatting(): ?string
    {
        return $this->inDeTaal($this->summary_nl, $this->summary_en);
    }

    /** "1-2 weken", of null. */
    public function duur(): ?string
    {
        return $this->inDeTaal($this->duration_nl, $this->duration_en);
    }

    /** Wat de klant na deze stap in handen heeft, of null. */
    public function resultaat(): ?string
    {
        return $this->inDeTaal($this->result_nl, $this->result_en);
    }

    /** Het hele verhaal voor /werkwijze, of null. */
    public function verhaal(): ?string
    {
        return $this->inDeTaal($this->body_nl, $this->body_en);
    }

    /**
     * Een veld in de taal van de bezoeker, zonder terugval.
     *
     * Staat het er in zijn taal niet, dan komt het er niet -- en niet in
     * de andere taal. Een Nederlandse zin tussen Engelse tekst leest als
     * een fout.
     */
    private function inDeTaal(?string $nederlands, ?string $engels): ?string
    {
        $tekst = $this->engels() ? $engels : $nederlands;

        return filled($tekst) ? (string) $tekst : null;
    }

    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }

    /**
     * Zoals de kaart op de landingspagina het nodig heeft.
     *
     * Het nummer zit er niet bij: dat telt het scherm af aan de plek in de
     * lijst. Zou de server het meesturen, dan kan het afwijken van de
     * volgorde waarin de kaarten staan.
     *
     * @return array<string, mixed>
     */
    public function voorDeSite(): array
    {
        return [
            'id' => $this->id,
            'titel' => $this->titel(),
            'samenvatting' => $this->samenvatting(),
            'duur' => $this->duur(),
            'resultaat' => $this->resultaat(),
            'verhaal' => $this->verhaal(),
        ];
    }

    /**
     * En zoals het beheerscherm het bewerkt: beide talen los.
     *
     * @return array<string, mixed>
     */
    public function voorHetScherm(): array
    {
        return [
            'id' => $this->id,
            // SortableList eist een string als sleutel.
            'key' => (string) $this->id,

            'published' => $this->published,

            'title_nl' => $this->title_nl,
            'title_en' => $this->title_en,
            'summary_nl' => $this->summary_nl,
            'summary_en' => $this->summary_en,
            'duration_nl' => $this->duration_nl,
            'duration_en' => $this->duration_en,
            'result_nl' => $this->result_nl,
            'result_en' => $this->result_en,
            'body_nl' => $this->body_nl,
            'body_en' => $this->body_en,

            'automatisch_vertaald' => $this->machine_translated_at !== null,
        ];
    }

    public static function activityName(): string
    {
        return __('Stap in je werkwijze');
    }

    public function activityLabel(): string
    {
        return $this->title_nl;
    }
}
