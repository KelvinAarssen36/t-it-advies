<?php

namespace App\Listeners;

use App\Enums\SecurityEventType;
use App\Support\Security\SecurityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Events\Dispatcher;
use Laravel\Fortify\Events\PasswordUpdatedViaController;
use Laravel\Fortify\Events\RecoveryCodeReplaced;
use Laravel\Fortify\Events\RecoveryCodesGenerated;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed;
use Laravel\Fortify\Events\TwoFactorAuthenticationDisabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationEnabled;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;

/**
 * Hangt de security-logging aan de auth- en Fortify-events.
 *
 * Alles loopt hier langs, zodat er maar een plek is waar je hoeft te kijken
 * om te zien wat er wel en niet wordt vastgelegd. Voeg je een gevoelige
 * handeling toe, hang hem dan hier aan of log expliciet via SecurityLogger.
 *
 * Let op bij Failed: $event->credentials bevat het opgegeven wachtwoord. Wij
 * halen daar alleen het e-mailadres uit en laten de rest liggen.
 *
 * De methoden heten record* en niet handle*. Laravel ontdekt listeners in
 * app/Listeners automatisch via methoden die met "handle" beginnen; met die
 * naamgeving zou elke gebeurtenis twee keer worden vastgelegd, een keer via
 * de ontdekking en een keer via subscribe().
 */
class RecordSecurityEvents
{
    public function __construct(private readonly SecurityLogger $logger) {}

    public function recordLogin(Login $event): void
    {
        $this->logger->success(SecurityEventType::Login, $event->user, [
            'guard' => $event->guard,
            'remember' => $event->remember,
        ]);
    }

    public function recordFailed(Failed $event): void
    {
        $this->logger->failure(
            SecurityEventType::LoginFailed,
            $event->user,
            ['guard' => $event->guard],
            $this->emailFromCredentials($event->credentials),
        );
    }

    public function recordLogout(Logout $event): void
    {
        $this->logger->success(SecurityEventType::Logout, $event->user, [
            'guard' => $event->guard,
        ]);
    }

    public function recordLockout(Lockout $event): void
    {
        $this->logger->failure(
            SecurityEventType::Lockout,
            context: ['path' => $event->request->path()],
            email: $this->emailFromCredentials((array) $event->request->only('email')),
        );
    }

    public function recordPasswordReset(PasswordReset $event): void
    {
        $this->logger->success(SecurityEventType::PasswordReset, $event->user);
    }

    public function recordPasswordUpdated(PasswordUpdatedViaController $event): void
    {
        $this->logger->success(SecurityEventType::PasswordUpdated, $event->user);
    }

    public function recordVerified(Verified $event): void
    {
        // Het Verified-event typeert $user als MustVerifyEmail, niet als
        // Authenticatable. In de praktijk is het onze User; we controleren
        // dat expliciet in plaats van het aan te nemen.
        $this->logger->success(
            SecurityEventType::EmailVerified,
            $event->user instanceof Authenticatable ? $event->user : null,
        );
    }

    public function recordTwoFactorEnabled(TwoFactorAuthenticationEnabled $event): void
    {
        $this->logger->success(SecurityEventType::TwoFactorEnabled, $event->user);
    }

    public function recordTwoFactorConfirmed(TwoFactorAuthenticationConfirmed $event): void
    {
        $this->logger->success(SecurityEventType::TwoFactorConfirmed, $event->user);
    }

    public function recordTwoFactorDisabled(TwoFactorAuthenticationDisabled $event): void
    {
        $this->logger->success(SecurityEventType::TwoFactorDisabled, $event->user);
    }

    public function recordTwoFactorChallenged(TwoFactorAuthenticationChallenged $event): void
    {
        $this->logger->success(SecurityEventType::TwoFactorChallenged, $event->user);
    }

    public function recordTwoFactorFailed(TwoFactorAuthenticationFailed $event): void
    {
        $this->logger->failure(SecurityEventType::TwoFactorFailed, $event->user);
    }

    public function recordTwoFactorSucceeded(ValidTwoFactorAuthenticationCodeProvided $event): void
    {
        $this->logger->success(SecurityEventType::TwoFactorSucceeded, $event->user);
    }

    public function recordRecoveryCodeUsed(RecoveryCodeReplaced $event): void
    {
        // Bewust zonder de code zelf, ook niet ingekort.
        $this->logger->success(SecurityEventType::RecoveryCodeUsed, $event->user);
    }

    public function recordRecoveryCodesGenerated(RecoveryCodesGenerated $event): void
    {
        $this->logger->success(SecurityEventType::RecoveryCodesGenerated, $event->user);
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            Login::class => 'recordLogin',
            Failed::class => 'recordFailed',
            Logout::class => 'recordLogout',
            Lockout::class => 'recordLockout',
            PasswordReset::class => 'recordPasswordReset',
            PasswordUpdatedViaController::class => 'recordPasswordUpdated',
            Verified::class => 'recordVerified',
            TwoFactorAuthenticationEnabled::class => 'recordTwoFactorEnabled',
            TwoFactorAuthenticationConfirmed::class => 'recordTwoFactorConfirmed',
            TwoFactorAuthenticationDisabled::class => 'recordTwoFactorDisabled',
            TwoFactorAuthenticationChallenged::class => 'recordTwoFactorChallenged',
            TwoFactorAuthenticationFailed::class => 'recordTwoFactorFailed',
            ValidTwoFactorAuthenticationCodeProvided::class => 'recordTwoFactorSucceeded',
            RecoveryCodeReplaced::class => 'recordRecoveryCodeUsed',
            RecoveryCodesGenerated::class => 'recordRecoveryCodesGenerated',
        ];
    }

    /**
     * @param  array<array-key, mixed>  $credentials
     */
    private function emailFromCredentials(array $credentials): ?string
    {
        $email = $credentials['email'] ?? null;

        return is_string($email) ? $email : null;
    }
}
