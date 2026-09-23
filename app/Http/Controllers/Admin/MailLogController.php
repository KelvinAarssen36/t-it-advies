<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MailStatus;
use App\Http\Controllers\Controller;
use App\Models\MailLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het mailoverzicht in het beveiligde gedeelte.
 *
 * Toont wat de applicatie heeft verstuurd en wat de provider daarover
 * heeft teruggemeld. Alleen metadata, geen mailinhoud.
 */
class MailLogController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString() ?: null,
            'status' => $request->string('status')->trim()->toString() ?: null,
        ];

        $logs = MailLog::query()
            ->when($filters['search'], fn ($query, string $search) => $query->where(
                fn ($query) => $query
                    ->where('subject', 'like', "%{$search}%")
                    ->orWhere('mailable', 'like', "%{$search}%")
                    ->orWhere('to', 'like', "%{$search}%")
            ))
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))
            ->latest('sent_at')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (MailLog $log) => [
                'id' => $log->id,
                'subject' => $log->subject,
                'mailable' => class_basename((string) $log->mailable),
                'to' => $log->to,
                'status' => $log->status->value,
                'status_label' => $log->status->label(),
                'is_problem' => $log->status->isProblem(),
                'sent_at' => $log->sent_at?->toDateTimeString(),
                'last_event_at' => $log->last_event_at?->toDateTimeString(),
                'events' => $log->events ?? [],
                'error' => $log->error,
            ]);

        return Inertia::render('admin/MailLog', [
            'logs' => $logs,
            'filters' => $filters,
            'statuses' => array_map(
                fn (MailStatus $status) => ['value' => $status->value, 'label' => $status->label()],
                MailStatus::cases(),
            ),
        ]);
    }
}
