<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * De kop van de landingspagina: het opschrift, de titel en de zin eronder.
 *
 * **Eén rij, altijd dezelfde.** Er is één landingspagina en dus één kop.
 * Gebruik `huidige()` en niet `find(1)`: die eerste levert ook op een
 * verse database iets bruikbaars op, zodat een vergeten seeder geen lege
 * voorpagina oplevert.
 *
 * De terugval tussen de talen werkt net als bij `ExperienceHeading`:
 *
 * - **Het opschrift** valt terug op het Nederlands. Op een telefoon is dit
 *   de functie op het visitekaartje onder de titel, en een visitekaartje
 *   zonder functie is stuk.
 * - **De titel** valt terug op het Nederlands, want een pagina zonder kop
 *   is stuk.
 * - **De zin eronder** doet dat niet. Half Nederlands op een Engelse
 *   pagina is slordiger dan geen zin.
 *
 * **Het activiteitenlogboek staat hier bewust op.** Dit is de eerste tekst
 * die een bezoeker leest; verandert die, dan wil je later kunnen terugzien
 * wanneer dat gebeurde en wat er stond.
 *
 * Zie docs/architecture/modules/kop.md.
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
class HeroHeading extends Model
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
     *
     * De tekst hier is dezelfde als in HeroHeadingSeeder. Dat is met opzet
     * dubbel: deze kant vangt een vergeten seeder op, die kant zet de rij
     * er echt neer zodat de klant hem kan aanpassen.
     */
    public static function huidige(): self
    {
        return self::query()->first() ?? new self([
            'eyebrow_nl' => __('IT-advies en realisatie'),
            'title_nl' => __('Techniek die doet wat je bedrijf nodig heeft.'),
            'intro_nl' => __('Van advies tot bouw en beheer. Zonder ruis, zonder afhankelijkheid van één leverancier.'),
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
        return __('Kop van de landingspagina');
    }

    public function activityLabel(): string
    {
        return $this->title_nl;
    }
}
