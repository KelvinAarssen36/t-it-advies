<?php

namespace App\Support\Security;

use App\Mail\CrashAlertMail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Stuurt een mail als de applicatie omvalt.
 *
 * Zonder dit ziet niemand een 500. Hij staat in `laravel.log`, en daar
 * kijkt niemand in tot de klant belt -- en dan is het al een dag oud. Dit
 * is geen vervanging van een dienst als Sentry, maar het verschil tussen
 * "we horen het" en "we horen het niet" is groter dan het verschil tussen
 * een mail en een dashboard.
 *
 * Vier grenzen, en ze zijn er allemaal om één reden: een melder die te veel
 * stuurt wordt weggefilterd, en dan ben je slechter af dan met geen melder.
 *
 * 1. **Alleen echte fouten.** Een 404 of een 403 is geen crash; die zeggen
 *    dat het systeem werkt.
 * 2. **Niet lokaal.** Tijdens het ontwikkelen zie je de fout op je scherm.
 * 3. **Een afkoeltijd per soort fout.** Eén kapotte pagina die tien keer
 *    wordt bezocht is één probleem, geen tien mails.
 * 4. **Geen adres ingesteld betekent stil blijven.** Een melder die zelf
 *    fouten gooit omdat hij niet kan mailen, is een probleem erbij.
 *
 * Zie docs/operations/monitoring.md.
 */
class CrashReporter
{
    public function __construct(private readonly SecurityLogger $logger) {}

    public function report(Throwable $exception, ?Request $request = null): void
    {
        if (! $this->moetMelden($exception)) {
            return;
        }

        $adres = config('security.alerts.address');

        if (! is_string($adres) || trim($adres) === '') {
            return;
        }

        /*
         * De sleutel is de soort fout plus de plek, niet de hele melding.
         * Een melding bevat vaak een id of een waarde die per verzoek
         * verschilt, en dan zou elke herhaling als een nieuw probleem
         * tellen -- precies wat de afkoeltijd moest voorkomen.
         */
        $sleutel = 'crash-alert:'.md5($exception::class.$exception->getFile().$exception->getLine());
        $minuten = (int) config('security.alerts.crash_cooldown_minutes', 30);

        if (! Cache::add($sleutel, true, now()->addMinutes(max($minuten, 1)))) {
            return;
        }

        try {
            Mail::to($adres)->send(new CrashAlertMail(
                soort: $exception::class,
                melding: $this->korteMelding($exception),
                plek: $this->plek($exception),
                adres: $this->pad($request),
                gebruiker: $request?->user()?->getAuthIdentifier(),
            ));
        } catch (Throwable $mislukt) {
            // Kan de mail niet weg, dan blijft het bij het logboek. Een
            // uitzondering vanuit de foutafhandeling levert alleen maar een
            // tweede fout op bovenop de eerste.
            Log::channel('security')->error('Crashmelding kon niet worden verstuurd.', [
                'reden' => $mislukt->getMessage(),
            ]);
        }
    }

    private function moetMelden(Throwable $exception): bool
    {
        if (app()->environment('local', 'testing')) {
            return false;
        }

        // Een 404, 403 of 419 is geen crash maar de applicatie die doet wat
        // hij hoort te doen.
        return ! $exception instanceof HttpExceptionInterface;
    }

    /**
     * De melding, ingekort en geschoond.
     *
     * Let op welke van de twee schoonmakers hier staat. `redact()` kijkt
     * naar sleutelnamen in een array en doet dus niets aan een lósse zin --
     * en een foutmelding is precies dat. `redactText()` kijkt in de tekst
     * zelf.
     *
     * Het is een vangnet en geen garantie; zie de toelichting daar. Regel 2
     * uit AGENTS.md blijft leidend: geheimen horen nergens in tekst die de
     * applicatie verlaat.
     */
    private function korteMelding(Throwable $exception): string
    {
        return Str::limit(
            $this->logger->redactText($exception->getMessage()),
            500,
        );
    }

    private function plek(Throwable $exception): string
    {
        // Alleen het pad binnen het project; de rest is de map van de
        // server en zegt niets.
        $bestand = Str::after($exception->getFile(), base_path().'/');

        return $bestand.':'.$exception->getLine();
    }

    /**
     * Het adres zonder de vraagtekenreeks.
     *
     * Daar kan een token in staan -- een wachtwoordherstel-link is het
     * duidelijkste voorbeeld -- en dat hoort niet in een mail.
     */
    private function pad(?Request $request): ?string
    {
        if ($request === null) {
            return null;
        }

        return $request->method().' '.$request->path();
    }
}
