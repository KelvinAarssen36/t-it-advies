<?php

namespace App\Http\Controllers\Settings;

use App\Enums\DashboardTimezone;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * De instellingen van het dashboard.
 *
 * **Dit scherm is er met opzet al terwijl er één instelling op staat.** De
 * eigenaar vroeg erom in die vorm: "zorg dus voor dat je in de instelling
 * een apart gedeelte dashboard instelling (...) want in de toekomst willen
 * we meer instelbare dingen maken voor het dashboard". De tijdzone van de
 * klok is de eerste; wat erbij komt hoort hier te landen en niet verspreid
 * te raken over het profiel en de weergave.
 *
 * Alles wat hier staat is een **persoonlijke** voorkeur en geen instelling
 * van de website. Vandaar bij de instellingen en niet onder Website: dit
 * verandert niets aan wat een bezoeker ziet.
 *
 * Zie docs/architecture/dashboard.md.
 */
class DashboardSettingsController extends Controller
{
    public function edit(Request $request): Response
    {
        /** @var User $gebruiker */
        $gebruiker = $request->user();

        return Inertia::render('settings/Dashboard', [
            'tijdzone' => $gebruiker->dashboardTijdzone()->value,
            'opties' => DashboardTimezone::opties(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'tijdzone' => ['required', Rule::enum(DashboardTimezone::class)],
        ]);

        /** @var User $gebruiker */
        $gebruiker = $request->user();

        $zone = DashboardTimezone::from($gegevens['tijdzone']);

        /*
         * Niets veranderd is geen opslag waard. Een melding "aangepast" bij
         * een opslag waarin niets gebeurde leert de eigenaar dat die
         * melding niets betekent -- dezelfde regel als bij elk ander
         * scherm in het portaal.
         */
        if ($gebruiker->dashboardTijdzone() === $zone) {
            Toast::melding(__('Er was niets veranderd.'));

            return back();
        }

        $gebruiker->dashboard_timezone = $zone;
        $gebruiker->save();

        Toast::bijgewerkt(
            __('De tijdzone van je klok is aangepast.'),
            $zone->label(),
        );

        return back();
    }
}
