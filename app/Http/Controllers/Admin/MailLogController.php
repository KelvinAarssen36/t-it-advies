<?php

namespace App\Http\Controllers\Admin;

use App\Enums\MailStatus;
use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Support\Datum;
use App\Support\Zoekterm;
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
            ->when($filters['search'], function ($query, string $search) {
                /*
                 * Via `Zoekterm`, want `_` betekent in een LIKE "één
                 * willekeurig teken" en staat in heel veel e-mailadressen.
                 * Dit scherm was de laatste plek die dat nog zelf deed, en
                 * sinds de bevestigingsmail staan hier adressen van
                 * bezoekers in -- dus zoeken op `jan_de_vries@...` mag niet
                 * ook het adres van iemand anders opleveren. Zie AGENTS.md.
                 */
                $patroon = Zoekterm::patroon($search);
                $teken = Zoekterm::TEKEN;

                $query->where(fn ($q) => $q
                    ->whereRaw("subject like ? escape '{$teken}'", [$patroon])
                    ->orWhereRaw("mailable like ? escape '{$teken}'", [$patroon])
                    ->orWhereRaw("`to` like ? escape '{$teken}'", [$patroon]));
            })
            ->when($filters['status'], fn ($query, string $status) => $query->where('status', $status))

            /*
             * Op `created_at` en niet op `sent_at`.
             *
             * **Anders zakt precies de interessante regel naar beneden.**
             * Een mail die nooit is verstuurd heeft geen `sent_at` -- zie
             * MeldtMislukteVerzending -- en NULL sorteert bij aflopend
             * sorteren achteraan. De mislukte verzending van vandaag kwam
             * dan onder de geslaagde van vorig jaar te staan. Voor een
             * geslaagde mail verandert er niets: `RecordOutgoingMail` zet
             * `sent_at` op hetzelfde moment als `created_at`.
             */
            ->latest('created_at')
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
                'sent_at' => Datum::tijdstip($log->sent_at),
                'last_event_at' => Datum::tijdstip($log->last_event_at),
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
