<?php

namespace App\Support\Security;

use App\Enums\MailStatus;
use App\Enums\SecurityEventType;
use App\Models\MailLog;
use App\Models\SecurityEvent;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Kijkt in het beveiligingslogboek en het mailoverzicht of er iets aan de
 * hand is dat iemand zou moeten zien.
 *
 * Bewust een losse klasse en niet alleen een commando: zo kun je dezelfde
 * vraag later ook vanuit een dashboard of een healthcheck stellen, en is hij
 * te testen zonder de scheduler.
 *
 * Wat hier NIET in komt: e-mailadressen van gebruikers en de inhoud van
 * mails. Een alarmeringsmail gaat naar een postbus die vaak minder goed is
 * beveiligd dan de applicatie zelf. Wie de details nodig heeft logt in op
 * /admin/security -- daar staat alles, achter 2FA en een recht.
 *
 * Zie docs/operations/onderhoudstaken.md.
 */
class AnomalyScanner
{
    /**
     * @return array<int, Anomaly> alleen de signalen die boven hun drempel uitkomen
     */
    public function scan(CarbonInterface $since): array
    {
        $anomalies = [
            $this->failedLogins($since),
            $this->mailProblems($since),
        ];

        return array_values(array_filter(
            $anomalies,
            fn (Anomaly $anomaly) => $anomaly->exceedsThreshold(),
        ));
    }

    /**
     * Mislukte inlogpogingen en blokkades door de rate limiter.
     *
     * Die twee tellen samen: een aanvaller die tegen de limiet aanloopt
     * levert juist minder `auth.login_failed` op, dus los van elkaar zou een
     * geslaagde afweer eruitzien als rust.
     */
    private function failedLogins(CarbonInterface $since): Anomaly
    {
        /** @var array<int, string> $details */
        $details = $this->failedLoginQuery($since)
            ->selectRaw('ip_address, count(*) as aantal')
            ->whereNotNull('ip_address')
            ->groupBy('ip_address')
            ->orderByDesc('aantal')
            ->limit(5)
            ->get()
            ->map(fn (SecurityEvent $row) => sprintf(
                '%s -- %d pogingen',
                (string) $row->ip_address,
                (int) $row->getAttribute('aantal'),
            ))
            ->all();

        return new Anomaly(
            key: 'failed-logins',
            title: __('Mislukte inlogpogingen'),
            count: $this->failedLoginQuery($since)->count(),
            threshold: (int) config('security.alerts.thresholds.failed_logins'),
            details: $details,
        );
    }

    /**
     * Bounces, spamklachten en mislukte verzendingen.
     */
    private function mailProblems(CarbonInterface $since): Anomaly
    {
        /** @var array<int, string> $details */
        $details = $this->mailProblemQuery($since)
            ->selectRaw('status, count(*) as aantal')
            ->groupBy('status')
            ->orderByDesc('aantal')
            ->get()
            ->map(fn (MailLog $row) => sprintf(
                '%s -- %d',
                $row->status->label(),
                (int) $row->getAttribute('aantal'),
            ))
            ->all();

        return new Anomaly(
            key: 'mail-problems',
            title: __('Problemen met uitgaande mail'),
            count: $this->mailProblemQuery($since)->count(),
            threshold: (int) config('security.alerts.thresholds.mail_problems'),
            details: $details,
        );
    }

    /**
     * @return Builder<SecurityEvent>
     */
    private function failedLoginQuery(CarbonInterface $since): Builder
    {
        return SecurityEvent::query()
            ->whereIn('event', [
                SecurityEventType::LoginFailed->value,
                SecurityEventType::Lockout->value,
            ])
            ->where('created_at', '>=', $since);
    }

    /**
     * We kijken naar `last_event_at` en naar `created_at`, want een mail kan
     * al bij het verzenden mislukken -- dan is er nooit een provider-event --
     * of pas dagen later bouncen, en dan is de regel zelf oud.
     *
     * @return Builder<MailLog>
     */
    private function mailProblemQuery(CarbonInterface $since): Builder
    {
        return MailLog::query()
            ->whereIn('status', [
                MailStatus::Bounced->value,
                MailStatus::Complained->value,
                MailStatus::Failed->value,
            ])
            ->where(fn ($query) => $query
                ->where('last_event_at', '>=', $since)
                ->orWhere('created_at', '>=', $since));
    }
}
