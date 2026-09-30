<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * De kop boven de diensten: het opschrift, de titel en de zin eronder.
 *
 * **Eén rij, altijd dezelfde.** Gebruik `huidige()` en niet `find(1)`:
 * die eerste levert ook op een verse database iets bruikbaars op, zodat
 * een vergeten seeder geen blok zonder kop oplevert. Hij slaat niets op
 * als de rij er nog niet is -- dit wordt ook aangeroepen bij het tonen
 * van de publieke site, en een GET hoort niets weg te schrijven.
 *
 * Dezelfde terugval als bij HeroHeading: het opschrift en de titel
 * vallen terug op het Nederlands, de zin eronder niet.
 *
 * Zie docs/architecture/modules/diensten.md.
 *
 * @property int $id
 * @property string $eyebrow_nl
 * @property string|null $eyebrow_en
 * @property string $title_nl
 * @property string|null $title_en
 * @property string|null $intro_nl
 * @property string|null $intro_en
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'eyebrow_nl',
    'eyebrow_en',
    'title_nl',
    'title_en',
    'intro_nl',
    'intro_en',
    'machine_translated_at',
])]
class ServiceHeading extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'machine_translated_at' => 'datetime',
        ];
    }

    /**
     * De enige rij, of een verse als hij er nog niet is.
     *
     * De tekst hier is dezelfde als in ServiceHeadingSeeder. Dat is met
     * opzet dubbel: deze kant vangt een vergeten seeder op, die kant
     * zet de rij er echt neer zodat de klant hem kan aanpassen.
     */
    public static function huidige(): self
    {
        return self::query()->first() ?? new self([
            'eyebrow_nl' => __('Diensten'),
            'title_nl' => __('Wat we doen'),
            'intro_nl' => __('Drie dingen, en die goed. De rest besteden we liever uit dan half te doen.'),
        ]);
    }

    /** Het opschrift boven de titel, met terugval op het Nederlands. */
    public function opschrift(): string
    {
        return $this->engels() && filled($this->eyebrow_en)
            ? (string) $this->eyebrow_en
            : $this->eyebrow_nl;
    }

    /** De titel, met terugval op het Nederlands. */
    public function titel(): string
    {
        return $this->engels() && filled($this->title_en)
            ? (string) $this->title_en
            : $this->title_nl;
    }

    /** De zin eronder, of null. Optioneel, dus zonder terugval. */
    public function inleiding(): ?string
    {
        $tekst = $this->engels() ? $this->intro_en : $this->intro_nl;

        return filled($tekst) ? (string) $tekst : null;
    }

    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }

    public static function activityName(): string
    {
        return __('Kop boven de diensten');
    }

    public function activityLabel(): string
    {
        return $this->title_nl;
    }
}
