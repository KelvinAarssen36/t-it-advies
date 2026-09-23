<?php

namespace App\Enums;

/**
 * De vaste namen van beveiligingsgebeurtenissen die we vastleggen.
 *
 * Gebruik deze enum in plaats van losse strings, zodat filters in het
 * beveiligde gedeelte en de tests niet uit elkaar kunnen lopen.
 */
enum SecurityEventType: string
{
    case Login = 'auth.login';
    case LoginFailed = 'auth.login_failed';
    case Logout = 'auth.logout';
    case Lockout = 'auth.lockout';
    case PasswordReset = 'auth.password_reset';
    case PasswordUpdated = 'auth.password_updated';
    case EmailVerified = 'auth.email_verified';

    case TwoFactorEnabled = '2fa.enabled';
    case TwoFactorConfirmed = '2fa.confirmed';
    case TwoFactorDisabled = '2fa.disabled';
    case TwoFactorChallenged = '2fa.challenged';
    case TwoFactorFailed = '2fa.failed';
    case TwoFactorSucceeded = '2fa.succeeded';
    case RecoveryCodeUsed = '2fa.recovery_code_used';
    case RecoveryCodesGenerated = '2fa.recovery_codes_generated';

    case SensitiveActionChallenged = 'sensitive.challenged';
    case SensitiveActionConfirmed = 'sensitive.confirmed';
    case SensitiveActionFailed = 'sensitive.failed';
    case SensitiveActionRecoveryCodeRefused = 'sensitive.recovery_code_refused';
    case SensitiveActionDenied = 'sensitive.denied';

    case SpamBlocked = 'spam.blocked';
    case TurnstileFailed = 'spam.turnstile_failed';
    case RateLimited = 'throttle.limited';

    case UserCreated = 'user.created';
    case UserRolesChanged = 'user.roles_changed';
    case UserDeleted = 'user.deleted';

    case WebhookRejected = 'webhook.rejected';
    case AlertSent = 'alert.sent';

    public function label(): string
    {
        return match ($this) {
            self::Login => __('Ingelogd'),
            self::LoginFailed => __('Mislukte login'),
            self::Logout => __('Uitgelogd'),
            self::Lockout => __('Login geblokkeerd'),
            self::PasswordReset => __('Wachtwoord hersteld'),
            self::PasswordUpdated => __('Wachtwoord gewijzigd'),
            self::EmailVerified => __('E-mailadres geverifieerd'),
            self::TwoFactorEnabled => __('2FA ingeschakeld'),
            self::TwoFactorConfirmed => __('2FA bevestigd'),
            self::TwoFactorDisabled => __('2FA uitgeschakeld'),
            self::TwoFactorChallenged => __('2FA gevraagd'),
            self::TwoFactorFailed => __('2FA mislukt'),
            self::TwoFactorSucceeded => __('2FA gelukt'),
            self::RecoveryCodeUsed => __('Recovery code gebruikt'),
            self::RecoveryCodesGenerated => __('Recovery codes aangemaakt'),
            self::SensitiveActionChallenged => __('Gevoelige actie: code gevraagd'),
            self::SensitiveActionConfirmed => __('Gevoelige actie: bevestigd'),
            self::SensitiveActionFailed => __('Gevoelige actie: verkeerde code'),
            self::SensitiveActionRecoveryCodeRefused => __('Gevoelige actie: recovery code geweigerd'),
            self::SensitiveActionDenied => __('Gevoelige actie: geen rechten'),
            self::SpamBlocked => __('Spam geblokkeerd'),
            self::TurnstileFailed => __('Turnstile mislukt'),
            self::RateLimited => __('Rate limit geraakt'),
            self::UserCreated => __('Gebruiker aangemaakt'),
            self::UserRolesChanged => __('Rollen van gebruiker gewijzigd'),
            self::UserDeleted => __('Gebruiker verwijderd'),
            self::WebhookRejected => __('Webhook geweigerd'),
            self::AlertSent => __('Alarmering verstuurd'),
        };
    }
}
