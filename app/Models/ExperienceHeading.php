<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * De kop boven de tijdlijn: de titel en de zin eronder.
 *
 * **Eén rij, altijd dezelfde.** Er is één tijdlijn en dus één kop. Gebruik
 * `huidige()` en niet `find(1)`: die eerste levert ook op een verse
 * database iets bruikbaars op, zodat een vergeten seeder geen lege kop op
 * de website oplevert.
 *
 * De terugval tussen de talen werkt net als bij `Experience`: de **titel**
 * valt terug op het Nederlands, want een blok zonder kop is stuk; de
 * **inleiding** doet dat niet, want half Nederlands op een Engelse pagina
 * is slordiger dan geen zin.
 *
 * **Het activiteitenlogboek staat hier bewust op.** Dit is tekst op de
 * voorpagina; verandert die, dan wil je later kunnen terugzien wanneer dat
 * gebeurde en wat er stond.
 *
 * Zie docs/architecture/modules/ervaring.md.
 *
 * @property int $id
 * @property string $title_nl
 * @property string|null $title_en
 * @property string|null $intro_nl
 * @property string|null $intro_en
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['title_nl', 'title_en', 'intro_nl', 'intro_en', 'machine_translated_at'])]
class ExperienceHeading extends Model
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
     * Niet opgeslagen als hij nieuw is: dit wordt ook aangeroepen bij het
     * tonen van de publieke site, en een GET hoort niets weg te schrijven.
     * De seeder legt de echte rij aan.
     */
    public static function huidige(): self
    {
        return self::query()->first() ?? new self([
            'title_nl' => __('Waar dit vandaan komt'),
            'intro_nl' => __('De weg ernaartoe, van nu naar toen.'),
        ]);
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
        return __('Kop boven de tijdlijn');
    }

    public function activityLabel(): string
    {
        return $this->title_nl;
    }
}
