<?php

namespace App\Models;

use App\Enums\PageSectionKey;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * De inhoud van het onderdeel "Over mij". Eén rij.
 *
 * **De verdeling is de hele module.** Er zijn twee plekken waar tekst van
 * de eigenaar terechtkomt, en die horen niet door elkaar te lopen:
 *
 * | Veld                                        | Waar het staat          |
 * | ------------------------------------------- | ----------------------- |
 * | `summary_*`                                 | Altijd op de voorpagina |
 * | `page_title_*`, `page_intro_*`, `story_*`, `photo_path` | Alleen op /over-mij |
 *
 * Daarom staat het beheerscherm in twee gelabelde blokken, en vergrijst het
 * tweede zolang de aparte pagina uit staat. Zie
 * resources/js/pages/website/OverMij.vue.
 *
 * **De samenvatting heeft een harde grens van 400 tekens.** Zonder die
 * grens wordt dit blok het hele levensverhaal midden op de voorpagina, en
 * dan is de aparte pagina er voor niets.
 *
 * Zie docs/architecture/modules/over-mij.md.
 *
 * @property int $id
 * @property string|null $summary_nl
 * @property string|null $summary_en
 * @property bool $page_enabled
 * @property string|null $page_title_nl
 * @property string|null $page_title_en
 * @property string|null $page_intro_nl
 * @property string|null $page_intro_en
 * @property string|null $story_nl
 * @property string|null $story_en
 * @property string|null $photo_path
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'summary_nl',
    'summary_en',
    'page_enabled',
    'page_title_nl',
    'page_title_en',
    'page_intro_nl',
    'page_intro_en',
    'story_nl',
    'story_en',
    'photo_path',
    'machine_translated_at',
])]
class AboutSetting extends Model
{
    use LogsActivity;

    /**
     * De langste samenvatting.
     *
     * **Dit getal staat ook in de browser**, in OverMijSamenvattingDialoog:
     * de teller onder het veld rekent met dezelfde grens. Lopen die twee
     * uiteen, dan krijgt de eigenaar een foutmelding op iets wat het scherm
     * net nog goedkeurde. Het gaat daarom als prop mee naar het scherm.
     */
    public const SAMENVATTING_MAX = 400;

    /** De langste lengte van het verhaal op de aparte pagina. */
    public const VERHAAL_MAX = 6000;

    /**
     * Hoeveel punten er onder het verhaal passen.
     *
     * Een ontwerpgrens en geen technische: tien korte regels vullen een
     * kolom, en daarboven leest het als een boodschappenlijst in plaats van
     * als een paar dingen die je wil zeggen.
     */
    public const PUNTEN_MAX = 10;

    /** Waar de eigen foto terechtkomt; zelfde opzet als bij een certificaat. */
    public const SCHIJF = 'public';

    public const MAP = 'over-mij';

    /**
     * De zijde van de uitsnede, in pixels.
     *
     * **Dezelfde maat als het medaillon**, en dat is de hele reden voor dit
     * getal: de eigen foto komt op precies dezelfde plek te staan, dus een
     * andere maat zou betekenen dat de pagina verspringt zodra hij er een
     * inzet. Zeshonderdveertig is bovendien het dubbele van waarop hij
     * wordt getoond, dus scherp op elk scherm.
     *
     * Het gaat als parameter mee naar `Logo::bewaar()`; die klasse rekent
     * verhoudingsgewijs, dus het voorbeeld in de browser hoeft dit getal
     * niet te kennen.
     */
    public const FOTO_MAAT = 640;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'page_enabled' => 'boolean',
            'machine_translated_at' => 'datetime',
        ];
    }

    /**
     * De instellingen, of een verse zolang er geen rij is.
     *
     * Niet opgeslagen als hij nieuw is: dit wordt ook aangeroepen bij het
     * tonen van de publieke site, en een GET hoort niets weg te schrijven.
     * Zelfde aanpak als `ContactSetting::huidige()`.
     */
    public static function huidige(): self
    {
        return self::query()->first() ?? new self;
    }

    /* --- De teksten, in de taal van de lezer ------------------------------ */

    /**
     * De samenvatting, of `null`.
     *
     * **Zonder terugval op het Nederlands.** Dat is anders dan bij de naam
     * van een dienst, en met opzet: een Engelse bezoeker die een
     * Nederlandse alinea over de eigenaar te lezen krijgt, krijgt iets wat
     * hij niet kan lezen op de plek waar hij vertrouwen moet opbouwen. Is
     * er geen Engels, dan valt het blok weg -- en dat is eerlijker. De
     * teller in AppServiceProvider rekent daarom met de taal mee.
     */
    public function samenvatting(): ?string
    {
        return $this->tekst($this->summary_nl, $this->summary_en);
    }

    /** De titel van de aparte pagina, of `null`. */
    public function paginaTitel(): ?string
    {
        return $this->tekst($this->page_title_nl, $this->page_title_en);
    }

    /** De inleiding op die pagina, of `null`. */
    public function paginaInleiding(): ?string
    {
        return $this->tekst($this->page_intro_nl, $this->page_intro_en);
    }

    /** Het verhaal op die pagina, of `null`. */
    public function verhaal(): ?string
    {
        return $this->tekst($this->story_nl, $this->story_en);
    }

    /* --- De foto ---------------------------------------------------------- */

    /**
     * Het beeld bij "Over mij".
     *
     * **Het medaillon is de standaard en niet een terugval bij een fout.**
     * Dat is het portret dat al in de hero staat: het oog-embleem uit de
     * huisstijl met het uitgeknipte portret erop, in vier maten. Eén bron,
     * meteen in de huisstijl, en niets om te uploaden.
     *
     * Zet de eigenaar er een eigen foto in, dan gaat die voor. Haalt hij
     * hem weer weg, dan staat het medaillon er weer -- hij kan dus niet in
     * een toestand komen zonder beeld.
     *
     * @return array{src: string, srcset: string|null, eigen: bool}
     */
    public function foto(): array
    {
        if (filled($this->photo_path)) {
            return [
                'src' => Storage::disk(self::SCHIJF)->url((string) $this->photo_path),
                'srcset' => null,
                'eigen' => true,
            ];
        }

        /*
         * Dezelfde vier maten als de hero. Ze staan hier als tekst en niet
         * in een lus, zodat je in één oogopslag ziet welke bestanden er
         * moeten bestaan; zie public/images.
         */
        return [
            'src' => '/images/persoon-medaillon-640.webp',
            'srcset' => implode(', ', [
                '/images/persoon-medaillon-320.webp 320w',
                '/images/persoon-medaillon-480.webp 480w',
                '/images/persoon-medaillon-640.webp 640w',
                '/images/persoon-medaillon-960.webp 960w',
            ]),
            'eigen' => false,
        ];
    }

    /* --- Wat de schermen nodig hebben ------------------------------------- */

    /**
     * De aparte pagina is pas echt bruikbaar met een verhaal erin.
     *
     * **Aan staan is niet hetzelfde als klaar zijn.** Zet de eigenaar het
     * schuifje om maar vult hij niets in, dan zou er een pagina met alleen
     * een kop komen te staan -- en een knop op zijn voorpagina die
     * daarheen wijst. Daarom kijkt de route hiernaar en niet alleen naar
     * `page_enabled`.
     */
    public function paginaStaatKlaar(): bool
    {
        return $this->page_enabled && filled($this->verhaal());
    }

    /**
     * Het korte blok, zoals de bezoeker het krijgt.
     *
     * `null` als er geen samenvatting in zijn taal is; dan hoort het blok
     * niet op de site te staan en regelt de teller dat ook.
     *
     * @return array{samenvatting: string, foto: array{src: string, srcset: string|null, eigen: bool}, pagina: bool}|null
     */
    public function voorDeSite(): ?array
    {
        $samenvatting = $this->samenvatting();

        if (blank($samenvatting)) {
            return null;
        }

        return [
            'samenvatting' => $samenvatting,
            'foto' => $this->foto(),
            'pagina' => $this->paginaStaatKlaar(),
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
            'summary_nl' => $this->summary_nl,
            'summary_en' => $this->summary_en,
            'page_enabled' => $this->page_enabled,
            'page_title_nl' => $this->page_title_nl,
            'page_title_en' => $this->page_title_en,
            'page_intro_nl' => $this->page_intro_nl,
            'page_intro_en' => $this->page_intro_en,
            'story_nl' => $this->story_nl,
            'story_en' => $this->story_en,
            'automatisch_vertaald' => $this->machine_translated_at !== null,

            /*
             * De foto zoals hij nu geldt, plus of het de eigen foto is. Dat
             * tweede bepaalt of er een knop "weghalen" bij staat -- het
             * medaillon is er altijd en valt niet weg te halen.
             */
            'foto' => $this->foto(),
        ];
    }

    /**
     * Of het onderdeel aanstaat op de indelingspagina.
     *
     * De aparte pagina heeft dit nodig: staat het onderdeel uit, dan wil de
     * eigenaar geen "Over mij", en dan hoort een eigen adres er ook niet te
     * zijn. Hetzelfde patroon als `Contactformulier::staatAan()`, inclusief
     * de terugval: is er nog nooit geseed, dan geldt alles als aan -- anders
     * hangt de site af van of iemand aan `db:seed` heeft gedacht.
     */
    public function sectieStaatAan(): bool
    {
        if (PageSection::query()->count() === 0) {
            return true;
        }

        return PageSection::query()
            ->aangezet()
            ->where('key', PageSectionKey::OverMij)
            ->exists();
    }

    /** De eigen foto weggooien, als er een is. */
    public function verwijderFoto(): void
    {
        $pad = (string) $this->photo_path;

        if ($pad === '') {
            return;
        }

        Storage::disk(self::SCHIJF)->delete($pad);
    }

    public static function activityName(): string
    {
        return __('Over mij');
    }

    public function activityLabel(): string
    {
        return __('Over mij');
    }

    /** De taal van de lezer, zonder terugval. */
    private function tekst(?string $nl, ?string $en): ?string
    {
        $waarde = app()->getLocale() === 'en' ? $en : $nl;

        return filled($waarde) ? (string) $waarde : null;
    }
}
