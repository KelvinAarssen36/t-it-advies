<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\AboutPointFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eén punt onder het verhaal op de pagina "Over mij".
 *
 * Korte regels die zeggen waar de eigenaar voor staat of goed in is. Ze
 * staan alleen op de aparte pagina, niet in het korte blok op de
 * voorpagina -- dat blok is één alinea en een foto.
 *
 * **Het Engels valt níet terug op het Nederlands.** Een rijtje met drie
 * Engelse en twee Nederlandse punten is slordiger dan een rijtje van drie,
 * dus een punt zonder Engels verdwijnt voor een Engelse bezoeker. Zelfde
 * keuze als bij een expertisepunt onder een dienst, en met opzet anders dan
 * bij een vraag in de FAQ -- daar is de vertaling de helft van het enige dat
 * er staat.
 *
 * Zie docs/architecture/modules/over-mij.md.
 *
 * @property int $id
 * @property int $position
 * @property string $text_nl
 * @property string|null $text_en
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'position',
    'text_nl',
    'text_en',
])]
class AboutPoint extends Model
{
    /** @use HasFactory<AboutPointFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpVolgorde(Builder $query): Builder
    {
        // De tweede sortering is geen overdaad: twee punten met dezelfde
        // positie zouden anders per query van plek wisselen.
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * De tekst in de taal van de lezer, of `null` als die er niet is.
     *
     * Zie de uitleg bovenaan: geen terugval, het punt valt weg.
     */
    public function tekst(): ?string
    {
        $waarde = app()->getLocale() === 'en' ? $this->text_en : $this->text_nl;

        return filled($waarde) ? (string) $waarde : null;
    }

    public static function activityName(): string
    {
        return __('Punt bij Over mij');
    }

    public function activityLabel(): string
    {
        return $this->text_nl;
    }
}
