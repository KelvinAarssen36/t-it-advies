<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Wat de eigenaar zelf in de voet van zijn site zet. Eén rij.
 *
 * **Niet alles in een voet hoort beheerbaar te zijn.** Dat is het
 * uitgangspunt van deze module, en het staat ook in het scherm. De indeling,
 * de kleuren, het jaartal, de privacylink en de sitemap liggen vast; wat de
 * eigenaar invult zijn de paar dingen die over zijn bedrijf gaan en die
 * niemand anders kan weten.
 *
 * **Leeg is weg.** Elk veld hier mag leeg blijven, en wat leeg is krijgt
 * `null` mee in plaats van een lege tekst. De voet beslist daarop of een
 * regel er staat; zie SiteFooter.vue.
 *
 * **De terugval verschilt per veld**, net als bij de kerngegevens:
 *
 * | Veld     | Engels leeg                                              |
 * | -------- | -------------------------------------------------------- |
 * | Tagline  | Weglaten -- een Nederlandse zin onder een Engels logo valt meer op dan geen zin |
 * | Plaats   | Terugvallen -- "Noord-Brabant" is in beide talen dezelfde plaats |
 * | Nummers  | Geen vertaling; een KvK-nummer is taalloos               |
 *
 * Zie docs/architecture/modules/voettekst.md.
 *
 * @property int $id
 * @property string|null $tagline_nl
 * @property string|null $tagline_en
 * @property Carbon|null $tagline_translated_at
 * @property string|null $phone
 * @property string|null $region_nl
 * @property string|null $region_en
 * @property Carbon|null $region_translated_at
 * @property string|null $kvk
 * @property string|null $vat
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'tagline_nl',
    'tagline_en',
    'phone',
    'region_nl',
    'region_en',
    'kvk',
    'vat',
    'tagline_translated_at',
    'region_translated_at',
])]
class FooterSetting extends Model
{
    use LogsActivity;

    /** De langste zin onder het merk. */
    public const TAGLINE_MAX = 120;

    /** De langste plaats of werkgebied. */
    public const PLAATS_MAX = 60;

    /** De langste telefoonnotatie. */
    public const TELEFOON_MAX = 32;

    /** De langste notatie van een KvK- of btw-nummer. */
    public const NUMMER_MAX = 20;

    /**
     * Hoeveel cijfers een KvK-nummer heeft.
     *
     * **Dit getal staat ook in het scherm**, onder het veld. Lopen die twee
     * uiteen, dan krijgt de eigenaar een foutmelding op iets wat het scherm
     * net goedkeurde; het gaat daarom als prop mee.
     */
    public const KVK_CIJFERS = 8;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tagline_translated_at' => 'datetime',
            'region_translated_at' => 'datetime',
        ];
    }

    /**
     * De instellingen, of een verse zolang er geen rij is.
     *
     * Niet opgeslagen als hij nieuw is: dit wordt bij elke publieke pagina
     * aangeroepen, en een GET hoort niets weg te schrijven. Zelfde aanpak
     * als `AboutSetting::huidige()`.
     */
    public static function huidige(): self
    {
        return self::query()->first() ?? new self;
    }

    /* --- De teksten, in de taal van de lezer ------------------------------ */

    /**
     * De zin onder het merk, of `null`.
     *
     * **Zonder terugval op het Nederlands**, net als de samenvatting bij
     * "Over mij". Deze zin staat direct onder een Engels menu en een Engelse
     * pagina; één Nederlandse regel daartussen leest als een fout. Is er
     * geen Engels, dan staat er niets, en dat valt niemand op.
     */
    public function tagline(): ?string
    {
        $waarde = app()->getLocale() === 'en' ? $this->tagline_en : $this->tagline_nl;

        return filled($waarde) ? (string) $waarde : null;
    }

    /**
     * De plaats of het werkgebied, of `null`.
     *
     * **Met terugval**, anders dan de tagline hierboven. Een plaatsnaam is
     * geen zin: "Noord-Brabant" zegt een Engelse bezoeker precies evenveel
     * als een Nederlandse, en hem die regel onthouden omdat er geen
     * vertaling is zou betekenen dat hij niet weet waar deze man zit.
     */
    public function plaats(): ?string
    {
        $waarde = app()->getLocale() === 'en' ? $this->region_en : $this->region_nl;

        if (blank($waarde)) {
            $waarde = $this->region_nl;
        }

        return filled($waarde) ? (string) $waarde : null;
    }

    /**
     * Het telefoonnummer als `tel:`-waarde, of `null`.
     *
     * Alles eruit wat geen cijfer of plusteken is. Een telefoon belt geen
     * spaties, en "06 12 34 56 78" moet dezelfde link worden als
     * "06-12345678".
     */
    public function telefoonLink(): ?string
    {
        if (blank($this->phone)) {
            return null;
        }

        $schoon = (string) preg_replace('/[^0-9+]/', '', (string) $this->phone);

        return $schoon === '' ? null : $schoon;
    }

    /* --- Wat de schermen nodig hebben ------------------------------------- */

    /**
     * De voet zoals de bezoeker hem krijgt.
     *
     * **Wat leeg is, is `null` en geen lege tekst.** Dat onderscheid doet
     * ertoe: de voet beslist met `v-if` of een regel er staat, en een lege
     * tekst is in JavaScript waar genoeg om een kopje boven niets te
     * zetten. De sleutels zitten er altijd allemaal in, zodat het type in
     * types/voettekst.ts één vorm beschrijft en niet twee.
     *
     * @return array{tagline: string|null, telefoon: array{tekst: string, link: string}|null, plaats: string|null, kvk: string|null, btw: string|null}
     */
    public function voorDeSite(): array
    {
        $link = $this->telefoonLink();

        return [
            'tagline' => $this->tagline(),

            'telefoon' => $link === null ? null : [
                'tekst' => (string) $this->phone,
                'link' => $link,
            ],

            'plaats' => $this->plaats(),
            'kvk' => filled($this->kvk) ? (string) $this->kvk : null,
            'btw' => filled($this->vat) ? (string) $this->vat : null,
        ];
    }

    /**
     * Alle velden los, zoals het beheerscherm ze bewerkt.
     *
     * @return array<string, mixed>
     */
    public function voorHetScherm(): array
    {
        return [
            'tagline_nl' => $this->tagline_nl,
            'tagline_en' => $this->tagline_en,
            'phone' => $this->phone,
            'region_nl' => $this->region_nl,
            'region_en' => $this->region_en,
            'kvk' => $this->kvk,
            'vat' => $this->vat,

            /*
             * Eén merkje per veld, en niet één voor allebei. Dat stond hier
             * eerst wel, en dan wiste het ene venster het merkje van het
             * andere; zie de migratie die de kolommen heeft gesplitst.
             */
            'tagline_automatisch_vertaald' => $this->tagline_translated_at !== null,
            'region_automatisch_vertaald' => $this->region_translated_at !== null,
        ];
    }

    public static function activityName(): string
    {
        return __('Voettekst');
    }

    public function activityLabel(): string
    {
        return __('Voettekst');
    }
}
