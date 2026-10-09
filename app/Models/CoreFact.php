<?php

namespace App\Models;

use App\Enums\CoreFactIcon;
use App\Models\Concerns\LogsActivity;
use Database\Factories\CoreFactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eén kerngegeven: een label met een waarde in tekst.
 *
 * "Beschikbaarheid -- vanaf januari, 2 tot 3 dagen per week". Samen vormen
 * ze de feitenstrook op de landingspagina.
 *
 * **Het verschil met een statistiek zit in de vorm van het antwoord.** Een
 * statistiek is een getal met een meter eromheen; het beeld draagt de
 * boodschap. Hier is het antwoord een zin, en "in overleg" is een geldig
 * antwoord. Daar past geen percentage omheen, en een meter zou nep zijn.
 *
 * **De terugval tussen de talen is hier beslist en niet in Vue**, zoals
 * overal in dit project. Hij verschilt per veld:
 *
 * | Veld    | Engels leeg                                                     |
 * | ------- | --------------------------------------------------------------- |
 * | Label   | Terugvallen -- een waarde zonder naam zegt niets.                |
 * | Waarde  | Terugvallen -- een KvK-nummer is in beide talen hetzelfde.       |
 * | Notitie | Weglaten -- de strook oogt zonder toelichting net zo goed.       |
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 *
 * @property int $id
 * @property CoreFactIcon $icon
 * @property bool $published
 * @property int $position
 * @property string $label_nl
 * @property string|null $label_en
 * @property string $value_nl
 * @property string|null $value_en
 * @property string|null $note_nl
 * @property string|null $note_en
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'icon',
    'published',
    'position',
    'label_nl',
    'label_en',
    'value_nl',
    'value_en',
    'note_nl',
    'note_en',
    'machine_translated_at',
])]
class CoreFact extends Model
{
    /** @use HasFactory<CoreFactFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * Hoeveel kerngegevens er hoogstens mogen staan.
     *
     * **Acht.** Een feitenstrook met vijftien regels is geen strook meer
     * maar een tabel, en dan leest niemand hem. Acht past op een rij van
     * vier in twee lagen, en het is precies het aantal soorten dat
     * `CoreFactIcon` kent.
     *
     * Dezelfde gedachte als `WorkStep::MAXIMUM`: een grens die de vorm
     * beschermt, niet de database.
     */
    public const MAXIMUM = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'icon' => CoreFactIcon::class,
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
        // De tweede sortering is geen overdaad: twee kerngegevens met
        // dezelfde positie zouden anders per query van plek wisselen.
        return $query->orderBy('position')->orderBy('id');
    }

    /** Het label, met terugval op het Nederlands. */
    public function label(): string
    {
        return $this->engels() && filled($this->label_en)
            ? (string) $this->label_en
            : $this->label_nl;
    }

    /** De waarde, met terugval op het Nederlands. */
    public function waarde(): string
    {
        return $this->engels() && filled($this->value_en)
            ? (string) $this->value_en
            : $this->value_nl;
    }

    /**
     * De toelichting, zónder terugval.
     *
     * Anders dan bij het label en de waarde: dit veld mag ontbreken, en
     * een Nederlandse zin tussen Engelse tekst valt meer op dan een
     * ontbrekende toelichting. Zelfde afweging als bij de notitie onder
     * een statistiek.
     */
    public function notitie(): ?string
    {
        $tekst = $this->engels() ? $this->note_en : $this->note_nl;

        return filled($tekst) ? (string) $tekst : null;
    }

    /**
     * Het kerngegeven zoals de bezoeker het krijgt.
     *
     * @return array{id: int, soort: string, label: string, waarde: string, notitie: string|null}
     */
    public function voorDeSite(): array
    {
        return [
            'id' => $this->id,
            'soort' => $this->icon->value,
            'label' => $this->label(),
            'waarde' => $this->waarde(),
            'notitie' => $this->notitie(),
        ];
    }

    /**
     * En zoals het beheerscherm het nodig heeft: alle talen naast elkaar.
     *
     * @return array<string, mixed>
     */
    public function voorHetScherm(): array
    {
        return [
            'id' => $this->id,

            // SortableList werkt met tekstsleutels; zie dat component.
            'key' => (string) $this->id,

            'icon' => $this->icon->value,
            'published' => $this->published,
            'position' => $this->position,
            'label_nl' => $this->label_nl,
            'label_en' => $this->label_en,
            'value_nl' => $this->value_nl,
            'value_en' => $this->value_en,
            'note_nl' => $this->note_nl,
            'note_en' => $this->note_en,
            'automatisch_vertaald' => $this->machine_translated_at !== null,
        ];
    }

    public static function activityName(): string
    {
        return __('Kerngegeven');
    }

    public function activityLabel(): string
    {
        return $this->label_nl;
    }

    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }
}
