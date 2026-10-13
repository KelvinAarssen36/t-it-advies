<?php

namespace App\Models;

use App\Enums\ProjectType;
use App\Models\Concerns\LogsActivity;
use App\Support\Datum;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Eén project uit de etalage van de eigenaar.
 *
 * **Dit is niet Ervaring, en dat verschil is de reden dat de module
 * bestaat.** Ervaring is zijn loopbaan: waar hij in dienst was, op een
 * tijdlijn, gesorteerd op datum, zonder dat hij er iets aan kan
 * verslepen. Dit is zijn etalage: wat hij heeft gedáán, in de volgorde
 * die hij zelf kiest, met hoogstens één stuk dat hij vooraan zet.
 *
 * **De terugval tussen de talen is hier beslist en niet in Vue.**
 *
 * | Veld                | Engels leeg                                |
 * | ------------------- | ------------------------------------------ |
 * | Titel, rol, type    | Terugvallen -- korte namen, en een kaart    |
 * |                     | zonder titel is stuk.                      |
 * | Samenvatting        | Weglaten -- de kaart blijft werken.         |
 * | Omschrijving        | Weglaten.                                   |
 * | Resultaat           | Weglaten.                                   |
 *
 * Het verschil zit in de soort tekst. Een titel en een rol zijn namen:
 * "Migratie Exchange" leest een Engelse bezoeker prima, en een kaart
 * zonder titel bestaat niet. Proza is iets anders -- een Nederlandse
 * alinea tussen Engelse tekst leest als een fout, en weglaten is dan
 * eerlijker. Zelfde afweging als bij Ervaring en Certificaten.
 *
 * De organisatie valt daarbuiten: een bedrijfsnaam is een eigennaam en
 * wordt niet vertaald.
 *
 * Zie docs/architecture/modules/projecten.md.
 *
 * @property int $id
 * @property int $position
 * @property bool $published
 * @property bool $featured
 * @property string $slug
 * @property ProjectType $type
 * @property string|null $type_label_nl
 * @property string|null $type_label_en
 * @property string $title_nl
 * @property string|null $title_en
 * @property string $organisation
 * @property string $role_nl
 * @property string|null $role_en
 * @property Carbon $started_on
 * @property Carbon|null $ended_on
 * @property string $summary_nl
 * @property string|null $summary_en
 * @property string|null $body_nl
 * @property string|null $body_en
 * @property string|null $result_nl
 * @property string|null $result_en
 * @property string|null $image_path
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'position',
    'published',
    'featured',
    'slug',
    'type',
    'type_label_nl',
    'type_label_en',
    'title_nl',
    'title_en',
    'organisation',
    'role_nl',
    'role_en',
    'started_on',
    'ended_on',
    'summary_nl',
    'summary_en',
    'body_nl',
    'body_en',
    'result_nl',
    'result_en',
    'image_path',
    'machine_translated_at',
])]
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory;

    use LogsActivity;

    /**
     * De schijf waarop de beelden staan.
     *
     * Dezelfde constanten als bij Ervaring en Certificaten, met opzet:
     * het model, het formulier en de opruiming moeten het over dezelfde
     * opslag hebben.
     */
    public const SCHIJF = 'public';

    /** De map binnen die schijf. */
    public const MAP = 'projecten';

    /**
     * De zijde van het bewaarde beeld, in beeldpunten.
     *
     * Groter dan de 256 van een logo, want dit beeld draagt het
     * uitgelichte blok en staat daar groot op het scherm. Zelfde maat
     * als het portret bij "Over mij", en om dezelfde reden: kleiner is
     * zichtbaar onscherp zodra het beeld meer dan een tegeltje vult.
     */
    public const BEELD_MAAT = 640;

    /** Hoeveel projecten er hoogstens naast het uitgelichte op de voorpagina staan. */
    public const OP_DE_VOORPAGINA = 3;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'published' => 'boolean',
            'featured' => 'boolean',
            'type' => ProjectType::class,
            'started_on' => 'date',
            'ended_on' => 'date',
            'machine_translated_at' => 'datetime',
        ];
    }

    /**
     * Het beeld opruimen zodra het project wordt verwijderd.
     *
     * Op `deleting` en niet op `deleted`: mislukt het verwijderen in de
     * database, dan is het bestand ook nog niet weg.
     */
    protected static function booted(): void
    {
        static::deleting(fn (self $project) => $project->verwijderBeeld());
    }

    /* --- Zoeken en sorteren ------------------------------------------- */

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
        // De tweede sortering is geen overdaad: twee projecten met
        // dezelfde positie zouden anders per query van plek wisselen.
        return $query->orderBy('position')->orderBy('id');
    }

    /**
     * De uitgelichte projecten, in de volgorde van de eigenaar.
     *
     * **Er mogen er meerdere zijn.** Staat er meer dan één ster, dan
     * draaien ze als slideshow -- bovenaan `/projecten` en in het blok op
     * de voorpagina. Staat er geen enkele, dan is er geen blok.
     *
     * **Met `online()` erin, en dat is de hele regel.** Zet de eigenaar
     * een uitgelicht project offline, dan verdwijnt het van de site -- er
     * wordt niet stil iets anders naar voren geschoven. Dat zou betekenen
     * dat de site iets toont wat hij niet heeft gekozen.
     *
     * @return Collection<int, self>
     */
    public static function uitgelichte(): Collection
    {
        return self::query()
            ->online()
            ->where('featured', true)
            ->opVolgorde()
            ->get();
    }

    /* --- De slug ------------------------------------------------------- */

    /**
     * Een vrij adres maken op basis van een titel.
     *
     * **Eén keer, bij het aanmaken, en daarna nooit meer.** Verandert de
     * titel later, dan blijft het adres staan: een link die iemand heeft
     * gedeeld of die in een zoekresultaat staat hoort te blijven werken.
     * Dat is belangrijker dan een adres dat altijd precies de huidige
     * titel spiegelt, en het scheelt een omleidingstabel.
     *
     * Levert `Str::slug` niets op -- een titel van alleen leestekens of
     * een niet-latijns schrift -- dan valt het terug op "project". Zonder
     * die terugval zou het adres leeg zijn en de unieke index klagen over
     * iets wat de eigenaar niet kan zien.
     */
    public static function vrijeSlug(string $titel): string
    {
        $basis = Str::slug($titel);

        if ($basis === '') {
            $basis = 'project';
        }

        $basis = Str::limit($basis, 150, '');
        $slug = $basis;
        $nummer = 1;

        while (self::query()->where('slug', $slug)->exists()) {
            $nummer++;
            $slug = $basis.'-'.$nummer;
        }

        return $slug;
    }

    /* --- De teksten, in de taal van de lezer --------------------------- */

    /** De titel, met terugval op het Nederlands. */
    public function titel(): string
    {
        return $this->engels() && filled($this->title_en)
            ? (string) $this->title_en
            : $this->title_nl;
    }

    /** De rol, met terugval op het Nederlands. */
    public function rol(): string
    {
        return $this->engels() && filled($this->role_en)
            ? (string) $this->role_en
            : $this->role_nl;
    }

    /**
     * Het woord op de badge.
     *
     * Bij een gewoon soort komt het uit de enum en is het dus centraal
     * vertaald. Bij "anders" komt het van de eigenaar, met terugval op
     * het Nederlands -- het is een kort label en geen proza, en een
     * badge zonder tekst is een leeg vlakje.
     */
    public function typeLabel(): string
    {
        if (! $this->type->eigenLabel()) {
            return $this->type->label();
        }

        $eigen = $this->engels() && filled($this->type_label_en)
            ? $this->type_label_en
            : $this->type_label_nl;

        return filled($eigen) ? (string) $eigen : $this->type->label();
    }

    /** De samenvatting, of null. Proza, dus zonder terugval. */
    public function samenvatting(): ?string
    {
        return $this->optioneel($this->summary_nl, $this->summary_en);
    }

    /** De omschrijving, of null. */
    public function omschrijving(): ?string
    {
        return $this->optioneel($this->body_nl, $this->body_en);
    }

    /** Wat het opleverde, of null. */
    public function resultaat(): ?string
    {
        return $this->optioneel($this->result_nl, $this->result_en);
    }

    /* --- De periode ---------------------------------------------------- */

    /** Loopt dit project nu nog? */
    public function loopt(): bool
    {
        return $this->ended_on === null;
    }

    /**
     * De periode als leesbare regel: "mrt 2021 – heden".
     *
     * Op de server en niet in de browser, net als bij Ervaring. Zou een
     * component dit opmaken, dan hangt de maandnaam af van de taal van
     * het besturingssysteem in plaats van die van de bezoeker.
     */
    public function periode(): string
    {
        $van = (string) Datum::maand($this->started_on);

        $tot = $this->loopt()
            ? (string) __('heden')
            : (string) Datum::maand($this->ended_on);

        return $van.' – '.$tot;
    }

    /**
     * Hoe lang het duurde: "2 jaar 3 maanden".
     *
     * De plus één is verplicht: maart tot en met maart is één maand werk
     * en niet nul. Zonder die plus staat er bij een korte opdracht
     * "0 maanden", en dat leest als een fout.
     */
    public function duur(): string
    {
        $einde = $this->ended_on ?? now();
        $maanden = (int) $this->started_on->diffInMonths($einde) + 1;

        $jaren = intdiv($maanden, 12);
        $rest = $maanden % 12;

        $delen = [];

        if ($jaren > 0) {
            $delen[] = $jaren === 1
                ? (string) __('1 jaar')
                : (string) __(':aantal jaar', ['aantal' => $jaren]);
        }

        if ($rest > 0 || $jaren === 0) {
            $delen[] = $rest === 1
                ? (string) __('1 maand')
                : (string) __(':aantal maanden', ['aantal' => max($rest, 1)]);
        }

        return implode(' ', $delen);
    }

    /* --- Het beeld ------------------------------------------------------ */

    /**
     * De volledige URL van het beeld, of null.
     *
     * De database bewaart alleen het pad. Er is geen standaardbeeld:
     * een project zonder foto hoort er gewoon te staan, en de kaart
     * vult die plek met de eerste letter van de organisatie in de
     * huisstijl. Een verzonnen plaatje zou suggereren dat er iets is.
     */
    public function beeld(): ?string
    {
        $pad = (string) $this->image_path;

        return $pad === '' ? null : Storage::disk(self::SCHIJF)->url($pad);
    }

    /** Het bestand weggooien, als er een is. */
    public function verwijderBeeld(): void
    {
        $pad = (string) $this->image_path;

        if ($pad === '') {
            return;
        }

        Storage::disk(self::SCHIJF)->delete($pad);
    }

    /* --- Wat de schermen nodig hebben ----------------------------------- */

    /**
     * Het project zoals een kaart op de website het nodig heeft.
     *
     * Alles al in de taal van de bezoeker en al opgemaakt. Het
     * component rekent niets uit en zoekt niets op.
     *
     * @return array<string, mixed>
     */
    public function voorDeKaart(): array
    {
        return [
            'slug' => $this->slug,
            'type' => $this->typeLabel(),
            'titel' => $this->titel(),
            'organisatie' => $this->organisation,
            'rol' => $this->rol(),
            'periode' => $this->periode(),
            'duur' => $this->duur(),
            'loopt' => $this->loopt(),
            'samenvatting' => $this->samenvatting(),
            'beeld' => $this->beeld(),
            'letter' => mb_strtoupper(mb_substr($this->organisation, 0, 1)),
        ];
    }

    /**
     * En zoals de detailpagina het nodig heeft.
     *
     * @return array<string, mixed>
     */
    public function voorDePagina(): array
    {
        return [
            ...$this->voorDeKaart(),
            'omschrijving' => $this->omschrijving(),
            'resultaat' => $this->resultaat(),
        ];
    }

    /**
     * Het project zoals het beheerscherm het nodig heeft: beide talen los.
     *
     * @return array<string, mixed>
     */
    public function voorHetScherm(): array
    {
        return [
            'id' => $this->id,

            // SortableList werkt met tekstsleutels; zie dat component.
            'key' => (string) $this->id,

            'published' => $this->published,
            'featured' => $this->featured,
            'slug' => $this->slug,

            'type' => $this->type->value,
            'type_naam' => $this->type->label(),
            'eigen_type' => $this->type->eigenLabel(),
            'type_label_nl' => $this->type_label_nl,
            'type_label_en' => $this->type_label_en,

            'title_nl' => $this->title_nl,
            'title_en' => $this->title_en,
            'organisation' => $this->organisation,
            'role_nl' => $this->role_nl,
            'role_en' => $this->role_en,

            'start' => $this->started_on->format('Y-m'),
            'eind' => $this->ended_on?->format('Y-m'),
            'loopt' => $this->loopt(),
            'periode' => $this->periode(),

            'summary_nl' => $this->summary_nl,
            'summary_en' => $this->summary_en,
            'body_nl' => $this->body_nl,
            'body_en' => $this->body_en,
            'result_nl' => $this->result_nl,
            'result_en' => $this->result_en,

            'beeld' => $this->beeld(),
            'letter' => mb_strtoupper(mb_substr($this->organisation, 0, 1)),
            'automatisch_vertaald' => $this->machine_translated_at !== null,
        ];
    }

    /** Of de Engelse versie geldt. */
    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }

    /** Een optioneel tekstveld in de juiste taal, zonder terugval. */
    private function optioneel(?string $nederlands, ?string $engels): ?string
    {
        $tekst = $this->engels() ? $engels : $nederlands;

        return filled($tekst) ? (string) $tekst : null;
    }

    public static function activityName(): string
    {
        return __('Project');
    }

    /** "Migratie Exchange (Zorgkoepel)" -- terug te herkennen in het logboek. */
    public function activityLabel(): string
    {
        return "{$this->title_nl} ({$this->organisation})";
    }
}
