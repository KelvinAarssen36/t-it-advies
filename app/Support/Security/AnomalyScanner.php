<?php

namespace App\Support\Security;

use App\Enums\MailStatus;
use App\Enums\SecurityEventType;
use App\Models\MailLog;
use App\Models\SecurityEvent;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

            /*
             * Deze kijkt met opzet niet naar `$since`.
             *
             * De andere twee tellen wat er in een tijdvak is gebeurd; deze
             * kijkt naar de stand van nu. "Er ligt werk dat een kwartier
             * over tijd is" is geen gebeurtenis in het verleden maar iets
             * dat op dit moment klemt, en dat blijft net zo waar als het
             * tijdvak groter of kleiner wordt.
             */
            $this->stuckQueue(),
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
     * Werk dat in de wachtrij blijft liggen.
     *
     * **Dit is de stilste storing die deze applicatie heeft.** Alle mail
     * gaat via de wachtrij: de melding aan de eigenaar, de bevestiging aan
     * de bezoeker, de beveiligingsmeldingen. Draait er geen worker, dan
     * blijven die in de tabel `jobs` staan en is er níets dat eruitziet
     * als een fout -- de bezoeker krijgt zijn bedankje, de aanvraag staat
     * in het portaal, en de eigenaar wacht op een mail die nooit komt.
     *
     * Vandaar dat het alarm hierover niet zelf via de wachtrij gaat; zie
     * ReportSecurityAnomalies.
     */
    private function stuckQueue(): Anomaly
    {
        $minuten = (int) config('security.alerts.stuck_job_minutes');
        $grens = CarbonImmutable::now()->subMinutes(max(1, $minuten));

        $jobs = $this->vastgelopenJobs($grens);

        /** @var array<int, string> $details */
        $details = [];

        if ($jobs !== null && $jobs['aantal'] > 0) {
            $details[] = __(':aantal stuks, de oudste staat er :tijd', [
                'aantal' => $jobs['aantal'],
                'tijd' => CarbonImmutable::createFromTimestamp($jobs['oudste'])
                    ->diffForHumans(syntax: CarbonInterface::DIFF_ABSOLUTE),
            ]);

            /*
             * Geen namen van jobs erbij. Een alarmeringsmail gaat naar een
             * postbus die vaak minder goed beveiligd is dan de applicatie;
             * de klasse van een job zegt wat er in de site gebeurt. Wie het
             * precies wil weten kijkt in het portaal.
             */
            $details[] = __('Dit betekent bijna altijd dat de queue worker niet draait.');
        }

        return new Anomaly(
            key: 'stuck-queue',
            title: __('Werk blijft in de wachtrij liggen'),
            count: $jobs['aantal'] ?? 0,
            threshold: (int) config('security.alerts.thresholds.stuck_jobs'),
            details: $details,
        );
    }

    /**
     * Hoeveel jobs over tijd zijn, en sinds wanneer de oudste.
     *
     * `null` als er niets te tellen valt: deze controle hangt aan de tabel
     * `jobs`, en die is er alleen bij de database-wachtrij. Staat er
     * `sync`, dan wordt werk tijdens het verzoek zelf gedaan en kan er per
     * definitie niets blijven liggen; staat er Redis, dan zit de wachtrij
     * ergens waar wij hier niet in kijken. In beide gevallen is zwijgen
     * juist -- een alarm dat altijd nul meldt leert je het te negeren.
     *
     * Ook jobs die een worker al heeft opgepakt tellen mee. Staat zo'n rij
     * er een kwartier later nog, dan is die worker onderweg gestopt, en dat
     * is net zo goed een storing.
     *
     * @return array{aantal: int, oudste: int}|null
     */
    private function vastgelopenJobs(CarbonInterface $grens): ?array
    {
        if (config('queue.default') !== 'database') {
            return null;
        }

        $tabel = (string) config('queue.connections.database.table', 'jobs');

        if (! Schema::hasTable($tabel)) {
            return null;
        }

        $rij = DB::table($tabel)
            ->where('available_at', '<=', $grens->getTimestamp())
            ->selectRaw('count(*) as aantal, min(available_at) as oudste')
            ->first();

        $aantal = (int) ($rij->aantal ?? 0);

        return [
            'aantal' => $aantal,
            'oudste' => (int) ($rij->oudste ?? $grens->getTimestamp()),
        ];
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
