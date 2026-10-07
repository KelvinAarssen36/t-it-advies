<?php

namespace App\Models;

use App\Enums\ProjectWeergave;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * De instellingen van de module Projecten, in één rij.
 *
 * Nu één: hoe de pagina met alle projecten eruitziet. Waarom dit een eigen
 * tabel is en niet `site_settings`, staat in de migratie.
 *
 * **Eén rij, net als `contact_settings`.** `huidige()` geeft die rij, of
 * een niet-opgeslagen exemplaar met de standaardwaarden als er nog nooit is
 * geseed. Zo werkt het scherm ook op een verse database zonder dat er een
 * controle op `null` door de hele applicatie heen loopt -- en zonder dat
 * het tonen van de publieke site iets wegschrijft.
 *
 * Zie docs/architecture/modules/projecten.md.
 *
 * @property int $id
 * @property ProjectWeergave $layout
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['layout'])]
class ProjectSetting extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'layout' => ProjectWeergave::class,
        ];
    }

    public static function huidige(): self
    {
        return self::query()->first() ?? new self(self::standaard());
    }

    /**
     * De stand waarmee een verse database begint.
     *
     * @return array<string, mixed>
     */
    public static function standaard(): array
    {
        return [
            'layout' => ProjectWeergave::Lijst,
        ];
    }

    /**
     * De weergave die nu geldt.
     *
     * Gaat via `huidige()` zodat elke aanroeper dezelfde terugval krijgt.
     */
    public static function weergave(): ProjectWeergave
    {
        return self::huidige()->layout;
    }

    public static function activityName(): string
    {
        return __('Instellingen van je projecten');
    }

    public function activityLabel(): string
    {
        return __('Weergave van de projectenpagina');
    }
}
