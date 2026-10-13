<?php

namespace App\Models;

use App\Enums\ExperienceStatKey;
use App\Enums\ExperienceStatModus;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eén cijfer boven de tijdlijn.
 *
 * **Hoogstens vier**, en dat is geen technische grens maar een keuze over
 * het ontwerp: vijf getallen naast elkaar boven een lijst is geen
 * samenvatting meer. De grens wordt in de validatie afgedwongen; zie
 * ExperienceController::kop().
 *
 * Drie dingen bepalen wat er op de website komt te staan:
 *
 * - **`key`** is het *soort*. Bij de eerste drie kunnen wij het getal uit
 *   de tijdlijn tellen; bij `eigen` niet, en dan moet er een getal in.
 * - **`modus`** zegt wat ermee gebeurt: uitrekenen, het eigen getal
 *   gebruiken, of helemaal niet tonen.
 * - **`label_nl` / `label_en`** zijn het woord eronder. Leeg betekent:
 *   gebruik het standaardwoord van dit soort. Dat is de stand waarin het
 *   woord vanzelf meegaat met de taal van de bezoeker.
 *
 * **Het activiteitenlogboek staat hier bewust op.** Een cijfer op de
 * voorpagina dat ineens anders is, is precies het soort verandering
 * waarvan je later wilt kunnen terugzien wanneer het gebeurde.
 *
 * @property int $id
 * @property ExperienceStatKey $key
 * @property string|null $label_nl
 * @property string|null $label_en
 * @property int|null $value
 * @property ExperienceStatModus $modus
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['key', 'label_nl', 'label_en', 'value', 'modus', 'position'])]
class ExperienceStat extends Model
{
    use LogsActivity;

    /** Meer dan dit passen er niet naast elkaar boven de tijdlijn. */
    public const MAXIMUM = 4;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => ExperienceStatKey::class,
            'value' => 'integer',
            'modus' => ExperienceStatModus::class,
            'position' => 'integer',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpVolgorde(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeZichtbaar(Builder $query): Builder
    {
        return $query->where('modus', '!=', ExperienceStatModus::Verborgen);
    }

    /**
     * Het woord onder het getal, in de taal van de bezoeker.
     *
     * Heeft de klant zelf niets ingevuld, dan komt het standaardwoord van
     * het soort eruit -- en dat is vertaald. Vandaar dat leeg laten hier
     * de betere keuze is dan het woord overtypen: dan blijft het ook op
     * de Engelse site kloppen.
     *
     * Een eigen woord valt terug op het Nederlands. Een cijfer zonder
     * woord eronder is stuk, dus hier is terugvallen beter dan weglaten.
     */
    public function woord(): string
    {
        $engels = app()->getLocale() === 'en';

        if ($engels && filled($this->label_en)) {
            return (string) $this->label_en;
        }

        if (filled($this->label_nl)) {
            return (string) $this->label_nl;
        }

        return $this->key->label();
    }

    public static function activityName(): string
    {
        return __('Cijfer op de tijdlijn');
    }

    public function activityLabel(): string
    {
        return $this->woord();
    }
}
