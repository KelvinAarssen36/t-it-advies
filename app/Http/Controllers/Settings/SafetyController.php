<?php

namespace App\Http\Controllers\Settings;

use App\Enums\MailStatus;
use App\Enums\SecurityEventType;
use App\Http\Controllers\Controller;
use App\Models\MailLog;
use App\Models\SecurityEvent;
use App\Models\User;
use App\Support\Datum;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Hoe deze website en dit portaal beveiligd zijn.
 *
 * **Een scherm dat niets doet, en dat is de bedoeling.** Er staat geen
 * knop op; het legt uit wat er onder water al gebeurt. De eigenaar vroeg
 * erom in die vorm: "een pagina die niet echt iets doet maar waarin gewoon
 * simpel staat hoe het allemaal beveiligd is".
 *
 * **Het is uitleg en geen controlelijst.** Eerst stond er bij elk punt een
 * vinkje of een kruisje; dat las als een keuring in plaats van als een
 * geruststelling, en de eigenaar heeft dat gecorrigeerd. Wat er nu staat
 * beschrijft wat er altijd gebeurt.
 *
 * **Rustig is niet hetzelfde als onwaar.** Een scherm dat zegt dat de
 * spamcontrole aanstaat terwijl de sleutel leeg is, is erger dan geen
 * scherm -- dan denkt hij beschermd te zijn. Daarom komen de vier waarden
 * hieronder nog steeds uit de instellingen en bepalen ze welke zin erbij
 * hoort, net als op de privacyverklaring.
 *
 * Het staat onder Instellingen en niet onder Beheer, want het hoort bij
 * "hoe zit mijn portaal in elkaar" en niet bij "wat is er gebeurd". Het
 * bestaande scherm Beveiliging gaat over zijn eigen wachtwoord en
 * tweestapsverificatie; dit gaat over het geheel.
 *
 * Zie docs/security/overzicht-voor-de-eigenaar.md.
 */
class SafetyController extends Controller
{
    /** Over hoeveel dagen de cijfers gaan. */
    private const DAGEN = 30;

    public function edit(Request $request): Response
    {
        /** @var User $gebruiker */
        $gebruiker = $request->user();

        $vanaf = now()->subDays(self::DAGEN);

        return Inertia::render('settings/Veiligheid', [
            'dagen' => self::DAGEN,

            /*
             * Echte cijfers uit de logboeken. Ze staan er niet om indruk
             * te maken maar omdat ze één vraag beantwoorden: gebeurt er
             * iets waar ik iets mee moet?
             */
            'cijfers' => [
                'mislukteLogins' => SecurityEvent::query()
                    ->whereIn('event', [
                        SecurityEventType::LoginFailed->value,
                        SecurityEventType::TwoFactorFailed->value,
                    ])
                    ->where('created_at', '>=', $vanaf)
                    ->count(),

                'geblokkeerd' => SecurityEvent::query()
                    ->whereIn('event', [
                        SecurityEventType::SpamBlocked->value,
                        SecurityEventType::TurnstileFailed->value,
                        SecurityEventType::RateLimited->value,
                        SecurityEventType::Lockout->value,
                    ])
                    ->where('created_at', '>=', $vanaf)
                    ->count(),

                'mailVerstuurd' => MailLog::query()
                    ->where('sent_at', '>=', $vanaf)
                    ->count(),

                'mailProblemen' => MailLog::query()
                    ->where('sent_at', '>=', $vanaf)
                    ->whereIn('status', [
                        MailStatus::Bounced->value,
                        MailStatus::Failed->value,
                    ])
                    ->count(),
            ],

            /*
             * Het scherm is uitleg en geen controlelijst, maar het mag
             * daardoor niet gaan beweren wat niet waar is. Deze vier
             * bepalen welke zin erbij hoort -- dezelfde aanpak als op de
             * privacyverklaring, waar Cloudflare alleen wordt genoemd als
             * de sleutel is ingevuld.
             *
             * De eerste twee gaan over wat de eigenaar zelf aanzet en
             * staan op het scherm apart, als uitnodiging en niet als
             * gebrek.
             */
            'beschermingen' => [
                'tweestaps' => $gebruiker->two_factor_confirmed_at !== null,
                'tweestapsSinds' => Datum::dag($gebruiker->two_factor_confirmed_at),
                'passkeys' => $gebruiker->passkeys()->count(),
                'spamcontrole' => filled(config('services.turnstile.site_key')),
                'alarmering' => filled(config('security.alerts.address')),
            ],

            'termijnen' => [
                'beveiliging' => (int) config('security.logging.retention_days'),
                'activiteit' => (int) config('security.logging.activity_retention_days'),
                'mail' => (int) config('mail.log_retention_days'),
            ],
        ]);
    }
}
