<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * Eén aanvraag om het inlogadres te wijzigen.
 *
 * **Het adres op `users` verandert pas bij `bevestig()`.** Tot dan is dit
 * een briefje met een voornemen; de eigenaar logt nog gewoon in met zijn
 * oude adres. Een typefout kan hem dus niet buitensluiten.
 *
 * **De twee tokens worden gehasht bewaard**, zoals een wachtwoord. Het
 * platte token bestaat één keer -- in de mail -- en nergens anders. Wie de
 * database leest kan er dus niets mee, en er belandt ook nooit een token
 * in een logregel.
 *
 * Zie docs/security/inlogadres-wijzigen.md.
 *
 * @property int $id
 * @property int $user_id
 * @property string $from_email
 * @property string $to_email
 * @property string $confirm_token
 * @property string|null $revert_token
 * @property Carbon $requested_at
 * @property Carbon $expires_at
 * @property Carbon|null $confirmed_at
 * @property Carbon|null $revert_expires_at
 * @property Carbon|null $reverted_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $user
 */
#[Fillable([
    'user_id',
    'from_email',
    'to_email',
    'confirm_token',
    'revert_token',
    'requested_at',
    'expires_at',
    'confirmed_at',
    'revert_expires_at',
    'reverted_at',
])]
class EmailChange extends Model
{
    /** Hoe lang de bevestigingslink geldig is, in minuten. */
    public const BEVESTIGEN_GELDIG = 60;

    /**
     * En hoe lang de weg terug openstaat, in dagen.
     *
     * Ruim, want dit is het vangnet. Merk je pas na een week dat je niet
     * meer binnenkomt -- bijvoorbeeld omdat je zelden inlogt -- dan moet
     * die link het nog doen.
     */
    public const HERSTELLEN_GELDIG = 14;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'expires_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'revert_expires_at' => 'datetime',
            'reverted_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /* --- Tokens -------------------------------------------------------- */

    /**
     * Een vers token: het platte deel voor de mail, het gehashte voor hier.
     *
     * `Str::random(48)` en geen `uniqid()` of een telling: dit is het enige
     * dat tussen een vreemde en een adreswijziging in staat, dus het hoort
     * uit een bron te komen die niet te raden is.
     *
     * @return array{0: string, 1: string}
     */
    public static function versToken(): array
    {
        $plat = Str::random(48);

        return [$plat, self::hash($plat)];
    }

    /** Dezelfde hash als bij het aanmaken; zonder zout, want het is al willekeurig. */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /* --- De stand ------------------------------------------------------- */

    /** Wacht deze aanvraag nog op bevestiging? */
    public function isOpen(): bool
    {
        return $this->confirmed_at === null
            && $this->reverted_at === null
            && $this->expires_at->isFuture();
    }

    /** Kan deze wijziging nog worden teruggedraaid? */
    public function isHerstelbaar(): bool
    {
        return $this->confirmed_at !== null
            && $this->reverted_at === null
            && $this->revert_expires_at !== null
            && $this->revert_expires_at->isFuture();
    }

    /**
     * De openstaande aanvraag van deze gebruiker, of null.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpenstaand(Builder $query): Builder
    {
        return $query
            ->whereNull('confirmed_at')
            ->whereNull('reverted_at')
            ->where('expires_at', '>', Carbon::now());
    }

    /**
     * Wat het scherm ervan hoeft te weten.
     *
     * **Zonder token**, vanzelfsprekend: dit gaat als prop naar de browser.
     *
     * @return array<string, mixed>
     */
    public function voorHetScherm(): array
    {
        return [
            'naar' => $this->to_email,
            'aangevraagd' => $this->requested_at->toIso8601String(),
            'verloopt' => $this->expires_at->toIso8601String(),
        ];
    }
}
