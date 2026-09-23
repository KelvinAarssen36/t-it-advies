<?php

use App\Console\Commands\PruneMailLogs;
use App\Console\Commands\PruneSecurityEvents;
use App\Console\Commands\ReportSecurityAnomalies;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Geplande taken
|--------------------------------------------------------------------------
|
| Deze taken draaien alleen als de scheduler draait. Op de server staat
| daarvoor één cronregel; zie docs/operations/deployment.md. Vergeet je die,
| dan groeien de logboektabellen door en komt er nooit een melding -- zonder
| dat er iets zichtbaar stuk is. Dat is precies waarom het in
| docs/operations/onderhoudstaken.md staat opgeschreven.
|
*/

// Elk uur, want een aanval die 's nachts begint wil je 's nachts zien en
// niet de volgende ochtend. De afkoeltijd in het commando zorgt dat dit geen
// stroom mails oplevert.
Schedule::command(ReportSecurityAnomalies::class)
    ->hourly()
    ->withoutOverlapping();

// Opruimen 's nachts, en niet op hetzelfde moment: twee grote deletes
// tegelijk op dezelfde database maken elkaar alleen maar trager.
Schedule::command(PruneSecurityEvents::class)->dailyAt('03:10');
Schedule::command(PruneMailLogs::class)->dailyAt('03:20');

// Huishouding van het framework zelf. Zonder dit groeien `failed_jobs`,
// `job_batches` en `password_reset_tokens` ook onbeperkt door.
Schedule::command('queue:prune-failed --hours=336')->dailyAt('03:30');
Schedule::command('queue:prune-batches')->dailyAt('03:40');
Schedule::command('auth:clear-resets')->dailyAt('03:50');
