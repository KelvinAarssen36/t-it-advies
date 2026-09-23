<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SecurityEventType;
use App\Enums\SecurityOutcome;
use App\Http\Controllers\Controller;
use App\Models\SecurityEvent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het beveiligingslogboek in het beveiligde gedeelte.
 *
 * Hier zie je geslaagde en mislukte pogingen: logins, 2FA, gevoelige acties,
 * geblokkeerde spam en geweigerde webhooks. De context van elke regel is al
 * geschoond door SecurityLogger, dus hier staan per definitie geen
 * wachtwoorden, codes of secrets in.
 */
class SecurityEventController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString() ?: null,
            'event' => $request->string('event')->trim()->toString() ?: null,
            'outcome' => $request->string('outcome')->trim()->toString() ?: null,
        ];

        $events = SecurityEvent::query()
            ->with('user:id,name,email')
            ->when($filters['search'], fn ($query, string $search) => $query->where(
                fn ($query) => $query
                    ->where('email', 'like', "%{$search}%")
                    ->orWhere('ip_address', 'like', "%{$search}%")
            ))
            ->when($filters['event'], fn ($query, string $event) => $query->where('event', $event))
            ->when($filters['outcome'], fn ($query, string $outcome) => $query->where('outcome', $outcome))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (SecurityEvent $event) => [
                'id' => $event->id,
                'event' => $event->event,
                'label' => $event->label(),
                'outcome' => $event->outcome->value,
                'outcome_label' => $event->outcome->label(),
                'user' => $event->user?->only(['id', 'name', 'email']),
                'email' => $event->email,
                'ip_address' => $event->ip_address,
                'user_agent' => $event->user_agent,
                'context' => $event->context,
                'created_at' => $event->created_at->toDateTimeString(),
            ]);

        return Inertia::render('admin/SecurityEvents', [
            'events' => $events,
            'filters' => $filters,
            'eventTypes' => array_map(
                fn (SecurityEventType $type) => ['value' => $type->value, 'label' => $type->label()],
                SecurityEventType::cases(),
            ),
            'outcomes' => array_map(
                fn (SecurityOutcome $outcome) => ['value' => $outcome->value, 'label' => $outcome->label()],
                SecurityOutcome::cases(),
            ),
        ]);
    }
}
