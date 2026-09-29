<?php

namespace App\Models;

use App\Enums\ExperienceStatKey;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Eén cijfer boven de tijdlijn, voor zover de klant het zelf invulde.
 *
 * De rijen worden geseed en niet aangemaakt door de klant: welke cijfers er
 * bestaan staat in ExperienceStatKey. Wat hij wél doet is er een getal in
 * zetten -- of dat weer leegmaken, en dan rekenen wij het weer uit.
 *
 * **Het activiteitenlogboek staat hier bewust op.** Een cijfer op de
 * voorpagina dat ineens anders is, is precies het soort verandering waarvan
 * je later wilt kunnen terugzien wanneer het gebeurde en wat er stond.
 *
 * @property int $id
 * @property ExperienceStatKey $key
 * @property int|null $value
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['key', 'value'])]
class ExperienceStat extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => ExperienceStatKey::class,
            'value' => 'integer',
        ];
    }

    public static function activityName(): string
    {
        return __('Cijfer op de tijdlijn');
    }

    /**
     * "jaar ervaring" en niet "jaren" -- hetzelfde woord dat de klant op
     * zijn website ziet staan.
     */
    public function activityLabel(): string
    {
        return $this->key->label();
    }
}
