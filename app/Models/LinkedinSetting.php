<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * De knoptekst van het LinkedIn-blok. Eén rij, of nog geen.
 *
 * De kop erboven (opschrift, titel, zin) staat in `SectionHeading`, zoals
 * bij elk onderdeel. Het adres staat in config/site.php en hoort hier
 * bewust niet: één bron voor de link, zie docs/architecture/modules/linkedin.md.
 *
 * **De terugval is per taal de standaardtekst, niet het Nederlands.** Een
 * lege Engelse knop wordt "View [naam]" en niet de Nederlandse tekst die
 * de eigenaar typte: een knop met een Nederlands woord op een Engelse
 * pagina leest als een fout, en "View [naam]" is altijd waar. Dat is
 * dezelfde gedachte als bij de zin onder een kop, met dit verschil dat een
 * knop zonder tekst geen optie is.
 *
 * @property int $id
 * @property string|null $button_nl
 * @property string|null $button_en
 * @property Carbon|null $button_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'button_nl',
    'button_en',
    'button_translated_at',
])]
class LinkedinSetting extends Model
{
    use LogsActivity;

    /** Kort: dit staat op een knop, en een knop van twee regels is geen knop. */
    public const KNOP_MAX = 40;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'button_translated_at' => 'datetime',
        ];
    }

    /**
     * De rij, of een lege als er nog niets is opgeslagen.
     *
     * Een lege rij geeft overal de standaardtekst, dus een verse
     * installatie -- of een herstelde back-up van vóór dit scherm -- toont
     * gewoon wat er altijd stond.
     */
    public static function huidige(): self
    {
        return self::query()->first() ?? new self;
    }

    /**
     * De tekst op de knop, in de taal van de bezoeker.
     *
     * @param  string  $naam  de naam van de eigenaar, voor de standaardtekst
     */
    public function knop(string $naam): string
    {
        $eigen = app()->getLocale() === 'en' ? $this->button_en : $this->button_nl;

        if (filled($eigen)) {
            return (string) $eigen;
        }

        return $naam !== ''
            ? (string) __('Bekijk :naam', ['naam' => $naam])
            : (string) __('Bekijk het profiel');
    }

    /**
     * @return array<string, mixed>
     */
    public function voorHetScherm(): array
    {
        return [
            'button_nl' => $this->button_nl,
            'button_en' => $this->button_en,
            'automatisch_vertaald' => $this->button_translated_at !== null,
        ];
    }

    public static function activityName(): string
    {
        return __('LinkedIn');
    }

    public function activityLabel(): string
    {
        return __('Knoptekst');
    }
}
