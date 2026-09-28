<?php

namespace App\Support\Activity;

use App\Enums\ActivityAction;
use App\Models\ActivityEntry;
use App\Models\User;
use App\Support\Security\SecurityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Legt vast wat er met de inhoud van de website gebeurt.
 *
 * Dit is het logboek van de **beheerder**, niet van de beveiliging. De vraag
 * die het beantwoordt is "wie heeft dit veranderd, en wat stond er eerst" --
 * niet "wie heeft geprobeerd binnen te komen". Die tweede staat in
 * SecurityLogger, en die scheiding is met opzet; zie docs/security/logging.md.
 *
 * Twee dingen zijn hier belangrijk.
 *
 * **Er wordt geschoond.** De oude en nieuwe waarden gaan door dezelfde
 * redact() als het beveiligingslogboek, met dezelfde lijst uit
 * config/security.php. Dat is geen dubbelop: een model kan een veld krijgen
 * dat gevoelig is zonder dat iemand eraan denkt, en dan hoort het logboek
 * niet de plek te zijn waar dat alsnog uitlekt. Regel 2 uit AGENTS.md geldt
 * hier net zo hard.
 *
 * **Er staat een momentopname in.** De naam van wie het deed en het label van
 * wat het was, staan als tekst in de rij. Wordt het onderdeel later
 * verwijderd of het account opgeheven, dan is de regel nog steeds te lezen.
 * Een logboek dat leeg loopt zodra het onderwerp verdwijnt, is precies op het
 * verkeerde moment nutteloos.
 */
class ActivityLogger
{
    /**
     * Velden die nooit interessant zijn om te loggen.
     *
     * Tijdstempels veranderen bij elke opslag, dus zonder deze lijst staat
     * er bij elke wijziging een regel "updated_at is veranderd" die de echte
     * wijziging wegdrukt.
     *
     * @var array<int, string>
     */
    private const GENEGEERD = ['created_at', 'updated_at', 'remember_token'];

    public function __construct(
        private readonly Request $request,
        private readonly SecurityLogger $security,
    ) {}

    public function created(Model $subject): ?ActivityEntry
    {
        return $this->record(ActivityAction::Created, $subject, $this->waarden($subject));
    }

    /**
     * Wat er bij deze opslag is veranderd, met de waarde van ervoor erbij.
     *
     * Dit hoort in de `updated`-gebeurtenis van het model te draaien en
     * nergens anders: alleen daar geeft `getChanges()` de nieuwe waarden en
     * `getOriginal()` nog de oude. Roep je het later aan, dan staat er twee
     * keer hetzelfde.
     */
    public function updated(Model $subject): ?ActivityEntry
    {
        $changes = [];

        foreach ($subject->getChanges() as $veld => $nieuw) {
            if ($this->slaOver($subject, $veld)) {
                continue;
            }

            $changes[$veld] = [
                'van' => $subject->getOriginal($veld),
                'naar' => $nieuw,
            ];
        }

        // Alleen een tijdstempel die opschuift is geen wijziging. Zonder
        // deze grens vult het logboek zich met lege regels.
        if ($changes === []) {
            return null;
        }

        return $this->record(ActivityAction::Updated, $subject, $changes);
    }

    public function deleted(Model $subject): ?ActivityEntry
    {
        // Bij verwijderen leggen we de laatste inhoud vast. Dat is het enige
        // moment waarop die anders voorgoed weg is, en juist bij een
        // verwijdering wil je achteraf kunnen zien wát er stond.
        return $this->record(ActivityAction::Deleted, $subject, $this->waarden($subject));
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public function record(
        ActivityAction $action,
        Model $subject,
        array $changes = [],
    ): ActivityEntry {
        $actor = Auth::user();

        return ActivityEntry::create([
            'user_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $this->naamVan($actor),
            'action' => $action,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'subject_label' => $this->labelVan($subject),
            'changes' => $this->security->redact($changes) ?: null,
            // In een commando of een geplande taak is er geen bezoeker, en
            // dan is het IP van de server geen informatie maar ruis.
            'ip_address' => app()->runningInConsole() ? null : $this->request->ip(),
        ]);
    }

    /**
     * De invulbare waarden van een model, voor aanmaken en verwijderen.
     *
     * @return array<string, mixed>
     */
    private function waarden(Model $subject): array
    {
        $waarden = [];

        foreach ($subject->attributesToArray() as $veld => $waarde) {
            if ($this->slaOver($subject, $veld)) {
                continue;
            }

            $waarden[$veld] = $waarde;
        }

        return $waarden;
    }

    /**
     * Of een veld buiten het logboek blijft.
     *
     * Naast de vaste lijst mag een model zelf velden uitsluiten met
     * `activityHidden()`. Dat is voor wat technisch is en niemand iets zegt
     * -- een sorteervolgorde die bij elke sleepactie verandert, een
     * cachesleutel -- en niet voor gevoelige waarden: die worden hoe dan ook
     * geschoond.
     */
    private function slaOver(Model $subject, string $veld): bool
    {
        if (in_array($veld, self::GENEGEERD, true)) {
            return true;
        }

        return method_exists($subject, 'activityHidden')
            && in_array($veld, $subject->activityHidden(), true);
    }

    private function labelVan(Model $subject): ?string
    {
        if (method_exists($subject, 'activityLabel')) {
            return Str::limit((string) $subject->activityLabel(), 200, '');
        }

        return null;
    }

    private function naamVan(mixed $actor): string
    {
        if ($actor instanceof User) {
            return $actor->name;
        }

        return __('Systeem');
    }
}
