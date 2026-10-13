<?php

namespace App\Models;

use App\Enums\PageSectionKey;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eén onderdeel van de landingspagina, op zijn plek in de rij.
 *
 * De rijen worden geseed en niet aangemaakt door de klant: welke onderdelen
 * er bestaan staat in PageSectionKey. Wat hij wél doet is ze verslepen en
 * aan- of uitzetten, en dat is precies wat hier staat.
 *
 * **Het activiteitenlogboek staat hier bewust wel op.** Een sectie die
 * ineens van de website is, is het soort verandering waarvan je een half
 * jaar later wilt kunnen terugzien wanneer het gebeurde. Een verplaatsing
 * levert een regel per verschoven onderdeel op -- schuif je iets van plek 3
 * naar plek 1, dan verschuiven er drie -- en dat is niet te veel maar
 * precies wat er gebeurd is.
 *
 * @property int $id
 * @property PageSectionKey $key
 * @property int $position
 * @property bool $visible
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['key', 'position', 'visible'])]
class PageSection extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => PageSectionKey::class,
            'visible' => 'boolean',
        ];
    }

    public static function activityName(): string
    {
        return __('Sectie');
    }

    /**
     * In het logboek staat "Diensten" en niet "diensten" -- dezelfde naam
     * die de klant op het indelingsscherm ziet.
     */
    public function activityLabel(): string
    {
        return $this->key->label();
    }

    /**
     * Van boven naar beneden, zoals ze op de pagina staan.
     *
     * `id` als tweede sleutel is geen overbodige zorgvuldigheid: zonder die
     * regel is de volgorde van twee onderdelen op dezelfde positie aan de
     * database, en die mag daar per keer anders over denken.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpVolgorde(Builder $query): Builder
    {
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * Wat de klant heeft aangezet.
     *
     * Aanstaan is niet hetzelfde als op de website staan: of er ook inhoud
     * in zit weet App\Support\Page\SectionContent, en dat is een vraag aan
     * een andere tabel. Die zeef zit daarom in de controller en niet hier.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeAangezet(Builder $query): Builder
    {
        return $query->where('visible', true);
    }

    public function vast(): bool
    {
        return $this->key->vast();
    }
}
