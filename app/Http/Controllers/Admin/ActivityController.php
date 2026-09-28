<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ActivityAction;
use App\Http\Controllers\Controller;
use App\Models\ActivityEntry;
use App\Support\Datum;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Het activiteitenlogboek: wie heeft wat aan de website veranderd.
 *
 * Dit scherm is er voor de klant zelf, en dat bepaalt wat je ziet. Geen
 * klassennamen en geen kolomnamen uit de database als het anders kan, maar
 * "Dienst", "Gewijzigd" en de titel van het onderdeel. De technische kant --
 * IP-adressen, uitzonderingen, inlogpogingen -- staat in het
 * beveiligingslogboek.
 *
 * Er staat géén kolom "wie". Het portaal heeft één gebruiker, dus daar zou
 * op elke regel dezelfde naam staan -- een kolom die alleen ruimte kost. De
 * naam wordt wél vastgelegd en is te zien als je een regel openklapt; komt
 * er ooit een tweede gebruiker, dan is die kolom een kwestie van terugzetten.
 *
 * Wat er in de plaats voor komt is nuttiger: welke velden er zijn gewijzigd,
 * en hoe lang geleden. Daarmee kun je de lijst scannen zonder elke regel
 * open te klappen.
 *
 * De soorten in het filter komen uit de tabel zelf en niet uit een lijst die
 * je moet bijhouden. Komt er een module bij, dan staat hij er vanzelf in
 * zodra er één regel van is; verdwijnt een module, dan verdwijnt hij uit het
 * filter maar blijft de geschiedenis leesbaar.
 */
class ActivityController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $request->string('search')->trim()->toString() ?: null,
            'action' => $request->string('action')->trim()->toString() ?: null,
            'subject' => $request->string('subject')->trim()->toString() ?: null,
        ];

        $entries = ActivityEntry::query()
            ->with('user:id,name')
            ->when($filters['search'], fn ($query, string $search) => $query
                ->where('subject_label', 'like', "%{$search}%"))
            ->when($filters['action'], fn ($query, string $action) => $query->where('action', $action))
            ->when($filters['subject'], fn ($query, string $subject) => $query->where('subject_type', $subject))
            ->latest('created_at')
            ->paginate(50)
            ->withQueryString()
            ->through(fn (ActivityEntry $entry) => [
                'id' => $entry->id,
                'action' => $entry->action->value,
                'action_label' => $entry->action->label(),
                'actor_name' => $entry->actor_name,
                'subject_name' => $entry->subjectName(),
                'subject_label' => $entry->subject_label,
                'changes' => $entry->changes,
                // De namen van de gewijzigde velden, zodat je in de lijst
                // ziet waar het over ging zonder de regel open te klappen.
                'changed_fields' => array_keys($entry->changes ?? []),
                'created_at' => Datum::tijdstip($entry->created_at),
                'created_at_diff' => Datum::geleden($entry->created_at),
            ]);

        return Inertia::render('admin/Activity', [
            'entries' => $entries,
            'filters' => $filters,
            'actions' => array_map(
                fn (ActivityAction $action) => ['value' => $action->value, 'label' => $action->label()],
                ActivityAction::cases(),
            ),
            'subjects' => $this->soorten(),
        ]);
    }

    /**
     * De soorten onderdelen waar iets van in het logboek staat.
     *
     * @return array<int, array{value: string, label: string}>
     */
    private function soorten(): array
    {
        return ActivityEntry::query()
            ->select('subject_type')
            ->distinct()
            ->orderBy('subject_type')
            ->get()
            ->map(fn (ActivityEntry $entry) => [
                'value' => $entry->subject_type,
                'label' => $entry->subjectName(),
            ])
            ->all();
    }
}
