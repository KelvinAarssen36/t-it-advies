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

    /*
     * Het inlogadres wijzigen, in drie stappen. Alle drie apart, want bij
     * een vraag achteraf wil je weten wáár het misging: is de aanvraag
     * gedaan, is hij bevestigd, en is hij teruggedraaid.
     */
    case EmailChangeRequested = 'auth.email_change_requested';
    case EmailChangeConfirmed = 'auth.email_change_confirmed';
    case EmailChangeReverted = 'auth.email_change_reverted';

    /*
     * De extra stap na een passkey. `Changed` is de schakelaar zelf --
     * vooral het uitzetten wil je kunnen terugvinden -- en `Challenged`
     * is elke keer dat de stap daadwerkelijk werd gevraagd.
     */
    case PasskeyStepChanged = 'auth.passkey_step_changed';
    case PasskeyStepChallenged = 'auth.passkey_step_challenged';

    /*
     * De back-ups van de website-inhoud. Alle vier apart, want bij een
     * vraag achteraf wil je kunnen zien wannéér er is teruggezet en
     * waarheen -- dat is de ingrijpendste handeling van het portaal.
     */
    case BackupGemaakt = 'backup.gemaakt';
    case BackupGecontroleerd = 'backup.gecontroleerd';
    case BackupTeruggezet = 'backup.teruggezet';
    case BackupVerwijderd = 'backup.verwijderd';

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

    /*
     * Gegevens die de beheerder op het scherm Juridisch heeft laten
     * verwijderen -- nu alleen mislukte mailpogingen. Het staat in dit
     * logboek omdat er gegevens door verdwijnen: zonder deze regel is er
     * later geen manier om te zien dat het is gebeurd.
     */
    case PrivacyDataCleared = 'privacy.data_cleared';

    /**
     * De Nederlandse tekst, onvertaald.
     *
     * In dit project is de Nederlandse zin zelf de vertaalsleutel, dus dit
     * is ook meteen de sleutel om in een andere taal op te zoeken. Dat is
     * nodig voor de antwoordtekst op het scherm Juridisch: die wordt in
     * allebei de talen tegelijk opgebouwd, en kan dus niet leunen op de
     * taal die het portaal op dat moment aanstaat.
     */
    public function sleutel(): string
    {
        return match ($this) {
            self::Login => 'Ingelogd',
            self::LoginFailed => 'Mislukte login',
            self::Logout => 'Uitgelogd',
            self::Lockout => 'Login geblokkeerd',
            self::PasswordReset => 'Wachtwoord hersteld',
            self::PasswordUpdated => 'Wachtwoord gewijzigd',
            self::EmailVerified => 'E-mailadres geverifieerd',
            self::EmailChangeRequested => 'Ander inlogadres aangevraagd',
            self::EmailChangeConfirmed => 'Inlogadres gewijzigd',
            self::EmailChangeReverted => 'Inlogadres teruggedraaid',
            self::PasskeyStepChanged => 'Extra stap na passkey gewijzigd',
            self::PasskeyStepChallenged => 'Extra stap na passkey gevraagd',
            self::BackupGemaakt => 'Back-up gemaakt',
            self::BackupGecontroleerd => 'Back-up gecontroleerd',
            self::BackupTeruggezet => 'Back-up teruggezet',
            self::BackupVerwijderd => 'Back-up verwijderd',
            self::TwoFactorEnabled => '2FA ingeschakeld',
            self::TwoFactorConfirmed => '2FA bevestigd',
            self::TwoFactorDisabled => '2FA uitgeschakeld',
            self::TwoFactorChallenged => '2FA gevraagd',
            self::TwoFactorFailed => '2FA mislukt',
            self::TwoFactorSucceeded => '2FA gelukt',
            self::RecoveryCodeUsed => 'Recovery code gebruikt',
            self::RecoveryCodesGenerated => 'Recovery codes aangemaakt',
            self::SensitiveActionChallenged => 'Gevoelige actie: code gevraagd',
            self::SensitiveActionConfirmed => 'Gevoelige actie: bevestigd',
            self::SensitiveActionFailed => 'Gevoelige actie: verkeerde code',
            self::SensitiveActionRecoveryCodeRefused => 'Gevoelige actie: recovery code geweigerd',
            self::SensitiveActionDenied => 'Gevoelige actie: geen rechten',
            self::SpamBlocked => 'Spam geblokkeerd',
            self::TurnstileFailed => 'Turnstile mislukt',
            self::RateLimited => 'Rate limit geraakt',
            self::UserCreated => 'Gebruiker aangemaakt',
            self::UserRolesChanged => 'Rollen van gebruiker gewijzigd',
            self::UserDeleted => 'Gebruiker verwijderd',
            self::WebhookRejected => 'Webhook geweigerd',
            self::AlertSent => 'Alarmering verstuurd',
            self::PrivacyDataCleared => 'Gegevens verwijderd op verzoek',
        };
    }

    public function label(): string
    {
        return __($this->sleutel());
    }
}
