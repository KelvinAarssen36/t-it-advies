<?php

namespace App\Models;

use App\Enums\DashboardTimezone;
use App\Models\Concerns\LogsActivity;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $locale
 * @property DashboardTimezone|null $dashboard_timezone
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Hoe dit onderdeel in het activiteitenlogboek heet.
     */
    public static function activityName(): string
    {
        return __('Account');
    }

    public function activityLabel(): string
    {
        return $this->name;
    }

    /**
     * Wat niet in het activiteitenlogboek komt.
     *
     * Dit is **geen** beveiligingsmaatregel -- ActivityLogger schoont
     * gevoelige sleutels hoe dan ook, en die lijst staat in
     * config/security.php. Deze velden staan hier omdat ze in dat logboek
     * niets toevoegen: alles rond tweestapsverificatie en wachtwoorden
     * staat al in het beveiligingslogboek, mét de context die daarbij
     * hoort. Twee keer hetzelfde vastleggen maakt allebei de logboeken
     * alleen maar slechter leesbaar.
     *
     * @return array<int, string>
     */
    public function activityHidden(): array
    {
        return [
            'password',
            'remember_token',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'two_factor_confirmed_at',
            'email_verified_at',
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'dashboard_timezone' => DashboardTimezone::class,
        ];
    }

    /**
     * De tijdzone van de klok op zijn dashboard.
     *
     * Een eigen methode en geen `->dashboard_timezone` op de aanroepplek,
     * want die kolom is leeg zolang niemand iets heeft gekozen -- en de
     * standaard hoort op één plek te staan. Een lege kolom met een `??`
     * bij elke lezer is precies hoe twee plekken uiteen gaan lopen.
     *
     * Een onbekende waarde uit de database levert `null` op door de cast,
     * en komt daarmee ook hier terecht. Dat is de bedoeling: een zone die
     * we niet kennen, kunnen we ook niet tonen.
     */
    public function dashboardTijdzone(): DashboardTimezone
    {
        return $this->dashboard_timezone ?? DashboardTimezone::STANDAARD;
    }

    /**
     * De QR-code voor het instellen van tweestapsverificatie.
     *
     * Overschrijft de versie uit Fortify om één reden: die genereert de code
     * met **marge 0**. De QR-standaard schrijft rondom een lichte rand van
     * vier modules voor, de zogeheten stille zone. Zonder die rand lukt het
     * scannen nog wel met de camera-app van een telefoon -- die is slim en
     * vergevingsgezind -- maar de eenvoudiger scanner in een
     * authenticator-app haakt af. Dat is precies het beeld waarmee dit aan
     * het licht kwam: camera goed, authenticator niet.
     *
     * Verder alleen de kleuren uit ons palet, en een iets groter formaat
     * zodat de modules niet kleiner worden door de marge erbij.
     *
     * Zie docs/security/authenticatie-en-2fa.md.
     */
    public function twoFactorQrCodeSvg(): string
    {
        $svg = (new Writer(
            new ImageRenderer(
                new RendererStyle(256, 4, null, null, Fill::uniformColor(
                    new Rgb(255, 255, 255),
                    new Rgb(6, 22, 38),
                )),
                new SvgImageBackEnd,
            )
        ))->writeString($this->twoFactorQrCodeUrl());

        return trim(substr($svg, strpos($svg, "\n") + 1));
    }
}
