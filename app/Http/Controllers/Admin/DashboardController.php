<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MailStatus;
use App\Enums\SecurityOutcome;
use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Models\SecurityEvent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Overzichtspagina van het beveiligde gedeelte.
 *
 * Bewust klein gehouden: een paar cijfers die je meteen vertellen of er iets
 * aan de hand is. Voor diepere analyse ga je door naar het mailoverzicht,
 * het beveiligingslogboek of Pulse.
 */
class DashboardController extends Controller
{
    public function index(): Response
    {
        $since = now()->subDay();

        return Inertia::render('admin/Dashboard', [
            'stats' => [
                'failed_security_events' => SecurityEvent::query()
                    ->where('outcome', SecurityOutcome::Failure)
                    ->where('created_at', '>=', $since)
                    ->count(),
                'security_events_total' => SecurityEvent::query()
                    ->where('created_at', '>=', $since)
                    ->count(),
                'mails_sent' => MailLog::query()
                    ->where('sent_at', '>=', $since)
                    ->count(),
                'mail_problems' => MailLog::query()
                    ->whereIn('status', [
                        MailStatus::Bounced->value,
                        MailStatus::Complained->value,
                        MailStatus::Failed->value,
                    ])
                    ->where('sent_at', '>=', $since)
                    ->count(),
            ],
            'recentFailures' => SecurityEvent::query()
                ->with('user:id,name,email')
                ->where('outcome', SecurityOutcome::Failure)
                ->latest('created_at')
                ->limit(10)
                ->get()
                ->map(fn (SecurityEvent $event) => [
                    'id' => $event->id,
                    'label' => $event->label(),
                    'email' => $event->email ?? $event->user?->email,
                    'ip_address' => $event->ip_address,
                    'created_at' => $event->created_at->toDateTimeString(),
                ])
                ->all(),
        ]);
    }
}
