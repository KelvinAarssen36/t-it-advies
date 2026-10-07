<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\FaqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eén veelgestelde vraag met zijn antwoord.
 *
 * Qua opzet de eenvoudigste module tot nu toe: één lijst, geen groepen,
 * geen bijlagen, geen tweede tabel. Wat er bijzonder aan is zit aan de
 * kant van de bezoeker -- zie FaqSection.vue.
 *
 * **De terugval tussen de talen is hier beslist en niet in Vue**, zoals
 * overal in dit project. En hier vallen ze **allebei** terug, anders dan
 * bij een optionele notitie onder een statistiek:
 *
 * | Veld     | Engels leeg                                                  |
 * | -------- | ------------------------------------------------------------ |
 * | Vraag    | Terugvallen -- een antwoord zonder vraag is onleesbaar.      |
 * | Antwoord | Terugvallen -- een vraag die opengaat en leeg is, is erger dan geen vraag. |
 *
 * Dat is een andere keuze dan bij de expertisepunten van een dienst, waar
 * een onvertaald label juist wordt wéggelaten. Het verschil: daar is het
 * één label in een rijtje van acht en valt er niets op, hier is het de
 * helft van het enige dat er staat.
 *
 * Zie docs/architecture/modules/faq.md.
 *
 * @property int $id
 * @property string $question_nl
 * @property string|null $question_en
 * @property string $answer_nl
 * @property string|null $answer_en
 * @property bool $published
 * @property int $position
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'question_nl',
    'question_en',
    'answer_nl',
    'answer_en',
    'published',
    'position',
    'machine_translated_at',
])]
class FaqItem extends Model
{
    /** @use HasFactory<FaqItemFactory> */
    use HasFactory;

    use LogsActivity;

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
        // De tweede sortering is geen overdaad: twee vragen met dezelfde
        // positie zouden anders per query van plek wisselen.
        return $query->orderBy('position')->orderBy('id');
    }

    /** De vraag, met terugval op het Nederlands. */
    public function vraag(): string
    {
        return $this->engels() && filled($this->question_en)
            ? (string) $this->question_en
            : $this->question_nl;
    }

    /** Het antwoord, met terugval op het Nederlands. */
    public function antwoord(): string
    {
        return $this->engels() && filled($this->answer_en)
            ? (string) $this->answer_en
            : $this->answer_nl;
    }

    /**
     * De vraag zoals de bezoeker hem krijgt.
     *
     * @return array{id: int, vraag: string, antwoord: string}
     */
    public function voorDeSite(): array
    {
        return [
            'id' => $this->id,
            'vraag' => $this->vraag(),
            'antwoord' => $this->antwoord(),
        ];
    }

    public static function activityName(): string
    {
        return __('Vraag');
    }

    public function activityLabel(): string
    {
        return $this->question_nl;
    }

    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }
}
