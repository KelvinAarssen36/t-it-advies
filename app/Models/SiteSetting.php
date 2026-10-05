<?php

namespace App\Models;

use App\Enums\MailStijl;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * De instellingen die over de hele site gaan, in één rij.
 *
 * Nu één: hoe de mail van deze website eruitziet. Waarom dit niet bij
 * `contact_settings` of bij de gebruiker staat, staat in de migratie.
 *
 * **Eén rij, net als `contact_settings`.** `huidige()` geeft die rij, of
 * een niet-opgeslagen exemplaar met de standaardwaarden als er nog nooit is
 * geseed. Zo werkt het scherm ook op een verse database zonder dat er een
 * controle op `null` door de hele applicatie heen loopt.
 *
 * Zie docs/architecture/mail-en-queues.md.
 *
 * @property int $id
 * @property MailStijl $mail_style
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable(['mail_style'])]
class SiteSetting extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mail_style' => MailStijl::class,
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
            'mail_style' => MailStijl::Licht,
        ];
    }

    /**
     * De stijl die nu geldt.
     *
     * Gaat via `huidige()` zodat elke aanroeper dezelfde terugval krijgt.
     * Alle mailables vragen het hier op; zie de trait
     * `VolgtDeMailstijl`.
     */
    public static function mailstijl(): MailStijl
    {
        return self::huidige()->mail_style;
    }

    public static function activityName(): string
    {
        return __('Instellingen van de website');
    }

    public function activityLabel(): string
    {
        return __('Mailstijl');
    }
}
