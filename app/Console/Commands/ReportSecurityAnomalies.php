<?php

namespace App\Console\Commands;

use App\Enums\SecurityEventType;
use App\Mail\SecurityAlertMail;
use App\Support\Security\Anomaly;
use App\Support\Security\AnomalyScanner;
use App\Support\Security\SecurityLogger;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Kijkt of er iets in het logboek staat waar iemand van moet weten, en mailt
 * dat.
 *
 * Een logboek waar niemand in kijkt is geen bewaking. Deze taak draait elk
 * uur en slaat aan zodra het aantal mislukte inlogpogingen of mailproblemen
 * binnen het venster boven de drempel komt.
 *
 * Twee keuzes die het verschil maken tussen nuttig en genegeerd worden:
 *
 * - Een afkoeltijd per soort signaal. Een aanval die drie uur duurt levert
 *   anders drie uur lang elk uur een mail op, en dan zet de ontvanger een
 *   filter aan -- precies het tegenovergestelde van wat we willen.
 * - Geen adres ingesteld betekent stil blijven, niet crashen. Een scheduler
 *   die elk uur een fout logt, leidt af van echte problemen.
 *
 * Zie docs/operations/onderhoudstaken.md.
 */
class ReportSecurityAnomalies extends Command
{
    protected $signature = 'security:report
                            {--window= : Aantal minuten om terug te kijken}
                            {--force : Negeer de afkoeltijd}';

    protected $description = 'Meld pieken in mislukte pogingen of mailproblemen per e-mail';

    public function handle(AnomalyScanner $scanner, SecurityLogger $logger): int
    {
        $option = $this->option('window');
        $window = is_numeric($option)
            ? (int) $option
            : (int) config('security.alerts.window_minutes');

        $since = now()->subMinutes(max($window, 1));
        $anomalies = $scanner->scan($since);

        if ($anomalies === []) {
            $this->info(__('Niets bijzonders in de laatste :minuten minuten.', ['minuten' => $window]));

            return self::SUCCESS;
        }

        $address = config('security.alerts.address');

        if (! is_string($address) || trim($address) === '') {
            // Bewust geen fout: de taak hoort te blijven draaien. Wel een
            // waarschuwing, want nu ziet niemand dit signaal.
            Log::warning('Alarmering overgeslagen: SECURITY_ALERT_ADDRESS is niet ingesteld.', [
                'signalen' => array_map(fn (Anomaly $anomaly) => $anomaly->key, $anomalies),
            ]);

            $this->warn(__('Er zijn signalen, maar SECURITY_ALERT_ADDRESS is niet ingesteld.'));

            return self::SUCCESS;
        }

        $report = array_values(array_filter($anomalies, $this->mayReport(...)));

        if ($report === []) {
            $this->info(__('Alle signalen zijn al gemeld en zitten nog in de afkoeltijd.'));

            return self::SUCCESS;
        }

        Mail::to($address)->send(new SecurityAlertMail($report, $since));

        $logger->success(SecurityEventType::AlertSent, context: [
            'signalen' => array_map(fn (Anomaly $anomaly) => [
                'sleutel' => $anomaly->key,
                'aantal' => $anomaly->count,
                'drempel' => $anomaly->threshold,
            ], $report),
        ]);

        foreach ($report as $anomaly) {
            $this->line(sprintf('%s: %d (drempel %d)', $anomaly->title, $anomaly->count, $anomaly->threshold));
        }

        $this->info(__('Melding verstuurd naar :adres.', ['adres' => $address]));

        return self::SUCCESS;
    }

    /**
     * Claimt de afkoeltijd voor dit signaal.
     *
     * `Cache::add` schrijft alleen als de sleutel er nog niet is en geeft
     * daarna false terug. Daardoor is claimen en controleren één handeling,
     * en kunnen twee gelijktijdige runs niet allebei dezelfde mail sturen.
     */
    private function mayReport(Anomaly $anomaly): bool
    {
        if ($this->option('force')) {
            return true;
        }

        return Cache::add(
            'security-alert:'.$anomaly->key,
            true,
            now()->addMinutes(max((int) config('security.alerts.cooldown_minutes'), 1)),
        );
    }
}
