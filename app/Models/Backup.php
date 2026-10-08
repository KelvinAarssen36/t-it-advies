<?php

namespace App\Models;

use App\Support\Datum;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Eén vastgelegde stand van de website-inhoud.
 *
 * De rij is de administratie; het bestand staat op de `local`-schijf, dus
 * in `storage/app/private/backups` en buiten de webroot.
 *
 * **Deze tabel zit zelf niet in een back-up.** Zou hij dat wel doen, dan
 * zou terugzetten de lijst met back-ups vervangen door de lijst van toen
 * -- en dan ben je de veiligheidskopie kwijt die je net had gemaakt. Zie
 * `Inhoudsregister::VERBODEN`.
 *
 * Zie docs/operations/back-ups.md.
 *
 * @property int $id
 * @property string $naam
 * @property Carbon $vastgelegd_op
 * @property string|null $bevestigcode
 * @property string $soort
 * @property string $bestand
 * @property int $grootte
 * @property string $checksum
 * @property array<string, int> $aantallen
 * @property string|null $schema_merk
 * @property string|null $site_merk
 * @property bool $vastgezet
 * @property Carbon|null $gecontroleerd_op
 * @property Carbon|null $gedownload_op
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'naam',
    'vastgelegd_op',
    'bevestigcode',
    'soort',
    'bestand',
    'grootte',
    'checksum',
    'aantallen',
    'schema_merk',
    'site_merk',
    'vastgezet',
    'gecontroleerd_op',
    'gedownload_op',
])]
class Backup extends Model
{
    /** De map op de `local`-schijf waar alles staat. */
    public const MAP = 'backups';

    /** En de map daarbinnen met de beelden, gedeeld door alle back-ups. */
    public const BEELDMAP = 'backups/media';

    /**
     * Hoeveel back-ups van jezelf er mogen staan.
     *
     * **Zelf gemaakt én geüpload samen.** Dat was eerst per soort, en dat
     * was een fout: het scherm beloofde "hoogstens vijf" terwijl er in de
     * praktijk vijf handmatige én vijf geüploade naast elkaar konden
     * staan. Een getal op het scherm dat de code niet waarmaakt is erger
     * dan geen getal.
     *
     * De veiligheidskopieën tellen hier níet in mee; zie
     * MAXIMUM_AUTOMATISCH. Vastgezette back-ups ook niet -- dat is het
     * hele punt van vastzetten.
     */
    public const MAXIMUM_EIGEN = 5;

    /**
     * En hoeveel er vastgezet mogen zijn.
     *
     * Twee. Meer maakt het vastzetten een tweede, ongelimiteerde lijst,
     * en dan is het maximum hierboven betekenisloos.
     */
    public const MAXIMUM_VAST = 2;

    /**
     * Hoeveel automatische veiligheidskopieën we bewaren.
     *
     * Die worden vlak vóór een terugzetting gemaakt en ruimen zichzelf
     * op. Ze tellen niet mee in MAXIMUM_EIGEN en vragen nooit om een
     * keuze: ze ontstaan midden in een handeling, en daar hoort geen
     * vraag doorheen te komen.
     *
     * **Dit zijn geen geplande back-ups.** Er draait niets per nacht of
     * per week; deze ontstaan alleen als de eigenaar iets terugzet. Zie
     * docs/operations/back-ups.md voor waarom dat zo is gelaten.
     */
    public const MAXIMUM_AUTOMATISCH = 3;

    public const SOORT_HANDMATIG = 'handmatig';

    public const SOORT_AUTOMATISCH = 'automatisch';

    public const SOORT_GEUPLOAD = 'geupload';

    /**
     * Vanaf wanneer een back-up "oud" heet, in dagen.
     *
     * Niet om iets te blokkeren -- alleen om het op het scherm en op het
     * dashboard te kunnen zeggen. Een back-up van drie maanden oud is
     * geen storing, maar je wil het wel weten.
     */
    public const OUD_NA_DAGEN = 60;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aantallen' => 'array',
            'vastgelegd_op' => 'datetime',
            'vastgezet' => 'boolean',
            'grootte' => 'integer',
            'gecontroleerd_op' => 'datetime',
            'gedownload_op' => 'datetime',
        ];
    }

    /* --- Het bestand -------------------------------------------------- */

    /** Het volledige pad op schijf, of null als het bestand weg is. */
    public function pad(): ?string
    {
        $schijf = Storage::disk('local');

        return $schijf->exists($this->bestand)
            ? $schijf->path($this->bestand)
            : null;
    }

    public function bestaat(): bool
    {
        return Storage::disk('local')->exists($this->bestand);
    }

    /* --- Scopes -------------------------------------------------------- */

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeVanSoort(Builder $query, string $soort): Builder
    {
        return $query->where('soort', $soort);
    }

    /**
     * Wat meetelt voor het maximum: niet vastgezet.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpruimbaar(Builder $query): Builder
    {
        return $query->where('vastgezet', false);
    }

    /**
     * De back-ups van de eigenaar zelf: gemaakt of geüpload.
     *
     * Dit is wat telt voor MAXIMUM_EIGEN. De veiligheidskopieën staan er
     * bewust buiten -- die zijn van het portaal en niet van hem.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeEigen(Builder $query): Builder
    {
        return $query->whereIn('soort', [self::SOORT_HANDMATIG, self::SOORT_GEUPLOAD]);
    }

    /**
     * Is dit er een van de eigenaar zelf?
     *
     * Een eigen methode náást de scope hierboven, want `$backup->eigen()`
     * zou de scope aanroepen en een querybuilder teruggeven -- en die is
     * altijd waar. Dat is precies het soort controle dat er goed uitziet
     * en nooit iets tegenhoudt.
     */
    public function isEigen(): bool
    {
        return in_array($this->soort, [self::SOORT_HANDMATIG, self::SOORT_GEUPLOAD], true);
    }

    /** Hoeveel eigen back-ups er staan die meetellen voor het maximum. */
    public static function eigenInGebruik(): int
    {
        return self::query()->eigen()->opruimbaar()->count();
    }

    public static function isVol(): bool
    {
        return self::eigenInGebruik() >= self::MAXIMUM_EIGEN;
    }

    /**
     * Hoeveel er te veel staan.
     *
     * Normaal nul. Boven nul kom je er wél: zet je er twee vast terwijl
     * je er vijf hebt -- vastgezette tellen niet mee -- en laat je ze
     * daarna los, dan staan er ineens zeven die meetellen. Ook een
     * oudere installatie kan er zo bij staan, want de grens gold
     * vroeger per soort.
     *
     * Dat is geen storing en er gaat niets stuk van. Maar het hoort niet
     * stil te blijven: het scherm vraagt er meteen om op te ruimen. Zie
     * BackupController::index().
     */
    public static function teveel(): int
    {
        return max(0, self::eigenInGebruik() - self::MAXIMUM_EIGEN);
    }

    /**
     * Hoeveel er aangewezen moeten worden als "oudste".
     *
     * Staan er te veel, dan zijn dat er net zoveel als er weg moeten --
     * de eigenaar moet kunnen zien wélke twee. Is de lijst alleen vol,
     * dan is er één aan de beurt zodra hij een nieuwe wil. En is er
     * ruimte, dan hoeft er niemand gekozen te worden en staat er dus ook
     * geen merkje.
     */
    public static function aantalOudsteTeTonen(): int
    {
        $teveel = self::teveel();

        if ($teveel > 0) {
            return $teveel;
        }

        /*
         * Eén, en altijd -- niet alleen bij een volle lijst. Dat was het
         * eerst wél, en dan staat er bij een oude back-up geen merkje
         * terwijl hij overduidelijk de oudste is. Een label dat soms
         * verschijnt leest als een fout, ook als de regel erachter
         * klopt.
         *
         * Bij één back-up heeft het geen zin: dan is dezelfde regel de
         * nieuwste én de oudste, en twee merkjes op één regel zeggen
         * niets.
         */
        return self::eigenInGebruik() >= 2 ? 1 : 0;
    }

    /**
     * Een viercijferige code die nog niet in gebruik is.
     *
     * Uniek onder de bestaande back-ups, zodat je er nooit eentje kunt
     * bevestigen met de code van een andere. Dat is geen beveiliging --
     * de code staat gewoon in het venster -- maar het voorkomt dat je het
     * verkeerde bestand terugzet omdat je het verkeerde venster openhad.
     */
    public static function verseCode(): string
    {
        $inGebruik = self::query()->pluck('bevestigcode')->filter()->all();

        do {
            $code = (string) random_int(1000, 9999);
        } while (in_array($code, $inGebruik, true));

        return $code;
    }

    /* --- Voor het scherm ------------------------------------------------ */

    /**
     * @param  bool  $nieuwste  de bovenste in de lijst
     * @param  bool  $oudste  de oudste die opgeruimd zou worden
     * @return array<string, mixed>
     */
    public function voorHetScherm(bool $nieuwste = false, bool $oudste = false): array
    {
        return [
            'id' => $this->id,
            'naam' => $this->naam,

            /*
             * De code mág naar de browser: hij staat in het venster om
             * overgetypt te worden. Hij is er om een handeling bewust te
             * maken, niet om iemand buiten te houden.
             */
            'bevestigcode' => $this->bevestigcode,

            'nieuwste' => $nieuwste,
            'oudste' => $oudste,
            'eigen' => $this->isEigen(),
            'soort' => $this->soort,
            'grootte' => $this->grootte,
            'groottePrettig' => self::prettigeGrootte($this->grootte),
            'aantallen' => $this->aantallen,
            'totaal' => array_sum($this->aantallen),
            'vastgezet' => $this->vastgezet,
            'bestaat' => $this->bestaat(),
            /*
             * De momentopname zelf, en niet wanneer de rij is gemaakt.
             * Bij een geüpload bestand lopen die twee uiteen: dan is de
             * rij van vandaag en de inhoud van januari. Het merkje
             * "Nieuwste" hoort bij de inhoud.
             */
            'gemaakt' => $this->vastgelegd_op->toIso8601String(),
            'gemaaktGeleden' => Datum::geleden($this->vastgelegd_op),
            'gemaaktOp' => $this->vastgelegd_op->translatedFormat('j F Y, H:i'),
            'gecontroleerd' => $this->gecontroleerd_op?->toIso8601String(),
            'gecontroleerdGeleden' => Datum::geleden($this->gecontroleerd_op),
            'gedownload' => $this->gedownload_op?->toIso8601String(),
        ];
    }

    /** "1,4 MB" in plaats van 1468006. */
    public static function prettigeGrootte(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 0, ',', '.').' KB';
        }

        return number_format($bytes / (1024 * 1024), 1, ',', '.').' MB';
    }
}
