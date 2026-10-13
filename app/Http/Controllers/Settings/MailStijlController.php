<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MailStijl;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use App\Support\Toast;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * De stijl waarin de website zijn mail verstuurt.
 *
 * Twee standen: licht of de huisstijl. De eigenaar zet het om op
 * Instellingen → Weergave en ziet het resultaat meteen in het voorbeeld
 * ernaast -- dat rendert de échte mail, dus wat hij ziet is wat er uitgaat.
 *
 * **Eén rij in `site_settings` en geen voorkeur bij de gebruiker.** De mail
 * gaat naar bezoekers, en die hebben geen account. Zie de migratie voor
 * waarom dit niet bij `contact_settings` staat.
 *
 * Zie App\Enums\MailStijl en docs/architecture/mail-en-queues.md.
 */
class MailStijlController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $gegevens = $request->validate([
            'stijl' => ['required', Rule::enum(MailStijl::class)],
        ]);

        $stijl = MailStijl::from($gegevens['stijl']);
        $instellingen = SiteSetting::huidige();

        if ($instellingen->exists && $instellingen->mail_style === $stijl) {
            Toast::melding(__('Die stijl stond al ingesteld.'));

            return back();
        }

        /*
         * `save()` op het exemplaar uit `huidige()`: dat is de bestaande
         * rij, of een nieuwe met de standaardwaarden als er nog nooit is
         * geseed. Zo hoeft hier geen `updateOrCreate` met een lege sleutel.
         */
        $instellingen->mail_style = $stijl;
        $instellingen->save();

        Toast::bijgewerkt(
            __('Je mailstijl staat nu op :stijl.', ['stijl' => $stijl->label()]),
            __('Elke mail die je website verstuurt gebruikt hem vanaf nu.'),
        );

        return back();
    }
}
