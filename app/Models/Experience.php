<?php

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\ExperienceIcon;
use App\Enums\WorkplaceType;
use App\Models\Concerns\LogsActivity;
use App\Support\Datum;
use Database\Factories\ExperienceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Eén ervaring op de tijdlijn: een functie bij een organisatie, in een
 * periode.
 *
 * **De taalregels staan hier en niet in de Vue-componenten.** Dat is met
 * opzet: welk veld terugvalt op het Nederlands en welk veld je beter
 * weglaat, is een inhoudelijke keuze en geen opmaak. Zou elk scherm dat
 * zelf beslissen, dan staat er vroeg of laat ergens half Nederlands op een
 * Engelse pagina.
 *
 * De twee regels:
 *
 * | Engels leeg  | Wat er gebeurt                                        |
 * | ------------ | ----------------------------------------------------- |
 * | Functie      | Terugvallen op het Nederlands -- zonder functietitel is de kaart stuk. |
 * | Plaats, tekst | Weglaten -- de kaart ziet er zonder ook goed uit.    |
 *
 * Zie docs/architecture/modules/ervaring.md.
 *
 * @property int $id
 * @property ExperienceIcon $icon
 * @property bool $published
 * @property string|null $logo_path
 * @property string $role_nl
 * @property string|null $role_en
 * @property string $organisation
 * @property string|null $organisation_url
 * @property EmploymentType|null $employment
 * @property WorkplaceType|null $workplace
 * @property string|null $location_nl
 * @property string|null $location_en
 * @property Carbon $started_on
 * @property Carbon|null $ended_on
 * @property string|null $description_nl
 * @property string|null $description_en
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'icon',
    'published',
    'logo_path',
    'role_nl',
    'role_en',
    'organisation',
    'organisation_url',
    'employment',
    'workplace',
    'location_nl',
    'location_en',
    'started_on',
    'ended_on',
    'description_nl',
    'description_en',
    'machine_translated_at',
])]
class Experience extends Model
{
    /** @use HasFactory<ExperienceFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * De schijf waarop de logo's staan.
     *
     * Hier en niet verspreid door de applicatie: het model, het formulier
     * en de opruiming moeten het over dezelfde opslag hebben, en een
     * losse string op drie plekken loopt uit elkaar. De schijf zelf staat
     * in config/filesystems.php en wijst naar `storage/app/public`, dat via
     * `php artisan storage:link` bereikbaar is. Zie
     * docs/operations/deployment.md.
     */
    public const SCHIJF = 'public';

    /** De map binnen die schijf. */
    public const MAP = 'ervaring';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'icon' => ExperienceIcon::class,
            'published' => 'boolean',
            'employment' => EmploymentType::class,
            'workplace' => WorkplaceType::class,
            'started_on' => 'date',
            'ended_on' => 'date',
            'machine_translated_at' => 'datetime',
        ];
    }

    /**
     * Het geüploade logo opruimen zodra de ervaring wordt verwijderd.
     *
     * Zonder deze haak blijft elk bestand staan van elke ervaring die ooit
     * is weggegooid. Dat merk je niet -- de site werkt gewoon -- tot de
     * schijf vol is en niemand meer weet welke bestanden nog ergens bij
     * horen.
     *
     * Op `deleting` en niet op `deleted`: mislukt het verwijderen in de
     * database, dan is het bestand ook nog niet weg. Andersom zou een
     * halfverwijderde ervaring zonder afbeelding achterblijven.
     */
    protected static function booted(): void
    {
        static::deleting(fn (self $ervaring) => $ervaring->verwijderLogo());
    }

    /**
     * Alleen wat de klant online heeft gezet.
     *
     * De tegenhanger van het schuifje in het beheerscherm. Dit staat als
     * scope op het model en niet als `where` in de controller, want er zijn
     * twee plekken die het moeten weten -- de website zelf en de teller die
     * bepaalt of het onderdeel nog inhoud heeft -- en die mogen niet uit
     * elkaar lopen.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /**
     * De volledige URL van het logo, of null.
     *
     * De database bewaart alleen het pad. Dat de schijf er een adres van
     * maakt is precies de bedoeling: verhuizen de bestanden ooit naar een
     * andere opslag, dan verandert er één regel in config/filesystems.php
     * en geen rij in de database.
     */
    public function logo(): ?string
    {
        $pad = (string) $this->logo_path;

        return $pad === '' ? null : Storage::disk(self::SCHIJF)->url($pad);
    }

    /** Het bestand weggooien, als er een is. */
    public function verwijderLogo(): void
    {
        $pad = (string) $this->logo_path;

        if ($pad === '') {
            return;
        }

        Storage::disk(self::SCHIJF)->delete($pad);
    }

    public static function activityName(): string
    {
        return __('Ervaring');
    }

    /**
     * "Systeembeheerder bij @T IT Advies" -- genoeg om de regel in het
     * logboek terug te herkennen zonder het item erbij te halen.
     */
    public function activityLabel(): string
    {
        return "{$this->role_nl} bij {$this->organisation}";
    }

    /**
     * De volgorde van de tijdlijn: wat nu loopt bovenaan, daarna op periode
     * van nieuw naar oud.
     *
     * Deze volgorde is niet instelbaar, en dat is een keuze. Een loopbaan
     * die niet op volgorde staat is gewoon fout, en een sleepgreep nodigt
     * uit tot die fout. Waar de volgorde wél redactioneel is -- de
     * onderdelen van de pagina bijvoorbeeld -- kan hij dat wel.
     *
     * `id` als laatste sleutel is geen overbodige zorgvuldigheid: zonder
     * die regel is de volgorde van twee ervaringen die in dezelfde maand
     * begonnen aan de database, en die mag daar per keer anders over
     * denken.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpTijdlijn(Builder $query): Builder
    {
        return $query
            ->orderByRaw('(ended_on is null) desc')
            ->orderByDesc('started_on')
            ->orderByDesc('id');
    }

    /**
     * Zoeken in het beheerscherm.
     *
     * Allebei de talen doen mee, en de plaats ook. Wie zijn tijdlijn in het
     * Engels nakijkt zoekt op de Engelse titel, en wie een oude functie
     * terugzoekt weet vaak alleen nog waar het was.
     *
     * De jokertekens van LIKE worden onschadelijk gemaakt. Zonder dat geeft
     * een zoekterm met een procentteken erin ineens alles terug, want in
     * een LIKE betekent `%` "wat dan ook". Geen lek -- de waarde is
     * gebonden -- maar wel een zoekveld dat raar doet zodra iemand "100%"
     * intypt.
     *
     * **De `ESCAPE`-clausule staat er expliciet bij, en dat is geen
     * overdaad.** MySQL neemt zonder die clausule de backslash als
     * ontsnappingsteken; SQLite -- waar de tests op draaien -- kent geen
     * standaardteken. Dan doet het zoekveld in een test iets anders dan in
     * productie, en dat is precies het soort verschil waardoor je een test
     * op een dag ten onrechte gelooft.
     *
     * **En het teken is een uitroepteken en geen backslash.** Dat scheelt
     * een tweede verschil tussen de twee: MySQL verwerkt backslashes ín
     * een tekst tussen aanhalingstekens, SQLite niet, dus `escape '\\'`
     * betekent daar niet hetzelfde. Een uitroepteken is in allebei gewoon
     * een uitroepteken.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeZoek(Builder $query, string $term): Builder
    {
        // Het ontsnappingsteken zelf gaat als eerste, anders ontsnapt hij
        // straks de tekens die we er net voor hebben gezet.
        $patroon = '%'.str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            $term,
        ).'%';

        $kolommen = [
            'role_nl',
            'role_en',
            'organisation',
            'location_nl',
            'location_en',
        ];

        return $query->where(function (Builder $query) use ($kolommen, $patroon) {
            foreach ($kolommen as $index => $kolom) {
                // De kolomnamen staan hierboven als vaste lijst; er komt
                // niets van de gebruiker in de query zelf terecht.
                $query->{$index === 0 ? 'whereRaw' : 'orWhereRaw'}(
                    "{$kolom} like ? escape '!'",
                    [$patroon],
                );
            }
        });
    }

    /** Loopt deze ervaring nog? */
    public function loopt(): bool
    {
        return $this->ended_on === null;
    }

    /**
     * De link naar de organisatie, of null als het er geen is.
     *
     * **Dit is een tweede slot op dezelfde deur, en dat is met opzet.** Het
     * formulier laat alleen `http` en `https` door, maar die controle zit
     * op de invoer. Deze zit op de uitvoer, en die is de laatste voordat er
     * `<a href="...">` van wordt gemaakt.
     *
     * Dat verschil doet ertoe: Vue schoont een `:href` niet, dus een waarde
     * als `javascript:...` die ooit langs de validatie komt -- via een
     * seeder, een import, of een regel die iemand later toevoegt -- zou
     * anders gewoon uitvoerbaar op de website belanden. Een link die
     * verdwijnt is een schoonheidsfoutje; een link die code uitvoert niet.
     */
    public function website(): ?string
    {
        $link = trim((string) $this->organisation_url);

        if ($link === '') {
            return null;
        }

        return str_starts_with($link, 'http://') || str_starts_with($link, 'https://')
            ? $link
            : null;
    }

    /**
     * De functietitel in de taal van de bezoeker.
     *
     * Verplicht veld, dus met terugval: een kaart zonder functietitel is
     * geen kaart.
     */
    public function functie(): string
    {
        return $this->engels() && filled($this->role_en)
            ? (string) $this->role_en
            : $this->role_nl;
    }

    /**
     * De plaats, of null.
     *
     * Optioneel veld, dus zonder terugval. Half Nederlands op een Engelse
     * pagina is slordiger dan geen plaats.
     */
    public function plaats(): ?string
    {
        return $this->optioneel($this->location_nl, $this->location_en);
    }

    public function beschrijving(): ?string
    {
        return $this->optioneel($this->description_nl, $this->description_en);
    }

    /**
     * De periode als leesbare regel: "mrt 2021 – heden".
     *
     * Alleen maand en jaar, want dat is wat er is ingevuld; de dag in de
     * database staat altijd op 1 en betekent niets.
     */
    public function periode(): string
    {
        $van = (string) Datum::maand($this->started_on);

        $tot = $this->loopt()
            ? __('heden')
            : (string) Datum::maand($this->ended_on);

        return "{$van} – {$tot}";
    }

    /**
     * Hoe lang het duurde: "2 jaar 3 maanden".
     *
     * Dit staat op LinkedIn naast de periode en het is de informatie
     * waarvoor je anders zelf zit te rekenen.
     */
    public function duur(): string
    {
        $einde = $this->ended_on ?? now();

        // Plus één maand, want maart tot en met maart is één maand werk en
        // niet nul. Zonder dat staat er bij een kort dienstverband
        // "0 maanden", en dat leest als een fout.
        $maanden = (int) $this->started_on->diffInMonths($einde) + 1;

        $jaren = intdiv($maanden, 12);
        $rest = $maanden % 12;

        $delen = [];

        if ($jaren > 0) {
            $delen[] = $jaren === 1
                ? __('1 jaar')
                : __(':aantal jaar', ['aantal' => $jaren]);
        }

        // Zijn er geen hele jaren, dan moet er sowieso iets staan -- en dan
        // is dat altijd minstens één maand, want de telling hierboven
        // begint bij 1.
        if ($rest > 0 || $jaren === 0) {
            $delen[] = $rest === 1
                ? __('1 maand')
                : __(':aantal maanden', ['aantal' => $rest]);
        }

        return implode(' ', $delen);
    }

    private function optioneel(?string $nederlands, ?string $engels): ?string
    {
        $waarde = $this->engels() ? $engels : $nederlands;

        return filled($waarde) ? $waarde : null;
    }

    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }
}
