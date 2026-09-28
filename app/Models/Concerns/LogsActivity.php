<?php

namespace App\Models\Concerns;

use App\Models\ActivityEntry;
use App\Support\Activity\ActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Str;

/**
 * Zet een model in het activiteitenlogboek.
 *
 * Gebruik hem op elk model waarvan de klant de inhoud beheert:
 *
 *     class Dienst extends Model
 *     {
 *         use LogsActivity;
 *
 *         public static function activityName(): string
 *         {
 *             return __('Dienst');
 *         }
 *
 *         public function activityLabel(): string
 *         {
 *             return $this->titel;
 *         }
 *     }
 *
 * **Waarom een trait en niet met de hand in elke controller.** Loggen dat je
 * handmatig doet, vergeet je een keer -- en juist dan wil je weten wat er is
 * gebeurd. Met deze trait hoort het bij het model, en dan is er geen weg
 * omheen: een `save()` vanuit een commando, een seeder of een toekomstig
 * scherm komt er net zo goed in.
 *
 * **Waarom het toch een keuze blijft.** Hij staat er niet vanzelf op elk
 * model. Een tabel met technische rijen -- een wachtrij, een cache -- hoort
 * niet in een logboek dat de klant leest. Je zet hem er dus bewust op, en
 * dat is precies de bedoeling.
 *
 * Zie docs/security/logging.md.
 */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::created(function (Model $model) {
            app(ActivityLogger::class)->created($model);
        });

        static::updated(function (Model $model) {
            app(ActivityLogger::class)->updated($model);
        });

        static::deleted(function (Model $model) {
            app(ActivityLogger::class)->deleted($model);
        });
    }

    /**
     * Hoe dit onderdeel heet in het logboek, in enkelvoud.
     *
     * Overschrijf dit met een `__()`-string zodra de klant het te zien
     * krijgt: "Dienst" leest beter dan "Service", en het vertaalt mee.
     */
    public static function activityName(): string
    {
        return Str::headline(class_basename(static::class));
    }

    /**
     * Waaraan je déze rij herkent: een titel, een naam, een onderwerp.
     *
     * Hij wordt als tekst in het logboek opgeslagen, niet als verwijzing.
     * Daardoor blijft de regel leesbaar nadat het onderdeel is verwijderd --
     * en dat is nu juist de regel die je later terugzoekt.
     */
    public function activityLabel(): string
    {
        return '#'.$this->getKey();
    }

    /**
     * Velden die niet in het logboek horen.
     *
     * Voor technische kolommen die niemand iets zeggen, zoals een
     * sorteervolgorde die bij elke sleepactie verandert. **Niet** voor
     * gevoelige waarden: die worden sowieso geschoond door ActivityLogger,
     * en daar hoort dit geen tweede, vergeetbare grendel voor te zijn.
     *
     * @return array<int, string>
     */
    public function activityHidden(): array
    {
        return [];
    }

    /**
     * De regels uit het logboek die over dit onderdeel gaan.
     *
     * @return MorphMany<ActivityEntry, $this>
     */
    public function activity(): MorphMany
    {
        return $this->morphMany(ActivityEntry::class, 'subject');
    }
}
