<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use App\Support\Datum;
use Database\Factories\CertificateFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Eén certificaat: wat hij heeft gehaald, bij wie, en wanneer.
 *
 * **Het logo van de uitgever draagt de tegel.** Een bezoeker scant
 * logo's die hij herkent -- Microsoft, Cisco, CompTIA -- en leest pas
 * daarna de naam eronder. Daarom is er geen keuzelijst met pictogrammen
 * zoals bij een dienst of een ervaring: het logo ís het pictogram, en
 * staat er geen, dan komt er één vast badge-teken.
 *
 * **De terugval tussen de talen is hier beslist en niet in Vue.** De
 * regel is overal dezelfde:
 *
 * | Veld        | Engels leeg                                          |
 * | ----------- | ---------------------------------------------------- |
 * | Naam        | Terugvallen -- een tegel zonder naam is stuk.        |
 * | Toelichting | Weglaten -- dan is de tegel gewoon niet aanklikbaar. |
 *
 * De uitgever staat er niet bij: "Microsoft" is een eigennaam en wordt
 * niet vertaald, net als `Experience::organisation`.
 *
 * **Een verstreken geldigheid haalt niets van de site, en de bezoeker
 * ziet er ook niets van.** Zie `verlopen()`.
 *
 * Zie docs/architecture/modules/certificaten.md.
 *
 * @property int $id
 * @property bool $published
 * @property int $position
 * @property string|null $logo_path
 * @property string $title_nl
 * @property string|null $title_en
 * @property string $issuer
 * @property Carbon $issued_on
 * @property Carbon|null $expires_on
 * @property string|null $credential_id
 * @property string|null $body_nl
 * @property string|null $body_en
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'published',
    'position',
    'logo_path',
    'title_nl',
    'title_en',
    'issuer',
    'issued_on',
    'expires_on',
    'credential_id',
    'body_nl',
    'body_en',
    'machine_translated_at',
])]
class Certificate extends Model
{
    /** @use HasFactory<CertificateFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * De schijf waarop de logo's staan.
     *
     * Dezelfde als bij de ervaringen, en met opzet dezelfde constanten
     * ernaast: het model, het formulier en de opruiming moeten het over
     * dezelfde opslag hebben.
     */
    public const SCHIJF = 'public';

    /** De map binnen die schijf. */
    public const MAP = 'certificaten';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'published' => 'boolean',
            'position' => 'integer',
            'issued_on' => 'date',
            'expires_on' => 'date',
            'machine_translated_at' => 'datetime',
        ];
    }

    /**
     * Het geüploade logo opruimen zodra het certificaat wordt verwijderd.
     *
     * Zonder deze haak blijft elk bestand staan van elk certificaat dat
     * ooit is weggegooid. Dat merk je niet -- de site werkt gewoon -- tot
     * de schijf vol is en niemand meer weet welke bestanden ergens bij
     * horen.
     *
     * Op `deleting` en niet op `deleted`: mislukt het verwijderen in de
     * database, dan is het bestand ook nog niet weg.
     */
    protected static function booted(): void
    {
        static::deleting(fn (self $certificaat) => $certificaat->verwijderLogo());
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOnline(Builder $query): Builder
    {
        return $query->where('published', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    public function scopeOpVolgorde(Builder $query): Builder
    {
        // De tweede sortering is geen overdaad: twee certificaten met
        // dezelfde positie zouden anders per query van plek wisselen.
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * Is de geldigheid verstreken?
     *
     * **Dit haalt het certificaat niet van de website, en de bezoeker
     * ziet het ook niet.** Behaald is behaald; iemand die op een
     * etalage kijkt hoeft niet te weten dat één papiertje aan
     * vernieuwing toe is. Er heeft een label op de tegel gestaan en dat
     * is er bewust weer af gehaald.
     *
     * Wat de website er wél mee doet is de geldigheidsdatum weglaten
     * zodra hij voorbij is -- "geldig tot" met een datum van vorig jaar
     * erachter zegt precies hetzelfde in andere woorden. Zie
     * HomeController.
     *
     * In het beheerscherm staat het juist nadrukkelijk: daar is het
     * iets om over te beslissen.
     *
     * De berekening staat hier en niet in Vue omdat er twee plekken op
     * leunen, en die mogen niet uiteen gaan lopen.
     */
    public function verlopen(): bool
    {
        return $this->expires_on !== null && $this->expires_on->isPast();
    }

    /** De naam van het certificaat, met terugval op het Nederlands. */
    public function naam(): string
    {
        return $this->engels() && filled($this->title_en)
            ? (string) $this->title_en
            : $this->title_nl;
    }

    /** De toelichting, of null. Optioneel, dus zonder terugval. */
    public function toelichting(): ?string
    {
        $tekst = $this->engels() ? $this->body_en : $this->body_nl;

        return filled($tekst) ? (string) $tekst : null;
    }

    /**
     * Valt er iets te lezen als je erop klikt?
     *
     * Zo niet, dan is de tegel geen knop. Dezelfde regel als bij een
     * dienst zonder lang verhaal: een venster dat opengaat met niets
     * erin is erger dan geen venster.
     */
    public function heeftDetails(): bool
    {
        return $this->toelichting() !== null || filled($this->credential_id);
    }

    /** "maart 2024", in de taal van de bezoeker. */
    public function behaald(): ?string
    {
        return Datum::maand($this->issued_on);
    }

    /** "maart 2027", of null als het niet verloopt. */
    public function geldigTot(): ?string
    {
        return Datum::maand($this->expires_on);
    }

    /**
     * De volledige URL van het logo, of null.
     *
     * De database bewaart alleen het pad. Dat de schijf er een adres van
     * maakt is precies de bedoeling: verhuizen de bestanden ooit naar
     * een andere opslag, dan verandert er één regel in
     * config/filesystems.php en geen rij in de database.
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

    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }

    public static function activityName(): string
    {
        return __('Certificaat');
    }

    /**
     * "Azure Administrator (Microsoft)" -- genoeg om de regel in het
     * logboek terug te herkennen zonder het item erbij te halen.
     */
    public function activityLabel(): string
    {
        return "{$this->title_nl} ({$this->issuer})";
    }
}
