<?php

namespace App\Models;

use App\Enums\PageSectionKey;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * De kop boven een onderdeel: het opschrift, de titel en de zin eronder.
 *
 * **Eén rij per onderdeel.** Hier stonden drie bijna identieke tabellen
 * -- `hero_headings`, `experience_headings` en `service_headings` -- en
 * elke module met een beheerbare kop maakte er een bij. Nu is het één
 * tabel met een `section`-kolom, en heeft elk toekomstig onderdeel gratis
 * een beheerbare kop.
 *
 * Gebruik `voor()` en niet `where(...)->first()`: die eerste levert ook
 * op een verse database iets bruikbaars op, zodat een vergeten seeder
 * geen lege kop op de website oplevert.
 *
 * **De terugval tussen de talen staat hier en niet in Vue.** De regel is
 * overal dezelfde:
 *
 * | Veld          | Engels leeg                                          |
 * | ------------- | ---------------------------------------------------- |
 * | Opschrift     | Terugvallen op het Nederlands -- het hoort bij de titel. |
 * | Titel         | Terugvallen -- een blok zonder kop is stuk.         |
 * | Zin eronder   | Weglaten -- half Nederlands op een Engelse pagina is slordiger dan geen zin. |
 *
 * **Het activiteitenlogboek staat hier bewust op.** Dit is tekst op de
 * voorpagina; verandert die, dan wil je later kunnen terugzien wanneer
 * dat gebeurde en wat er stond.
 *
 * Zie docs/architecture/kopteksten.md.
 *
 * @property int $id
 * @property PageSectionKey $section
 * @property string|null $eyebrow_nl
 * @property string|null $eyebrow_en
 * @property string $title_nl
 * @property string|null $title_en
 * @property string|null $intro_nl
 * @property string|null $intro_en
 * @property Carbon|null $machine_translated_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'section',
    'eyebrow_nl',
    'eyebrow_en',
    'title_nl',
    'title_en',
    'intro_nl',
    'intro_en',
    'machine_translated_at',
])]
class SectionHeading extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'section' => PageSectionKey::class,
            'machine_translated_at' => 'datetime',
        ];
    }

    /**
     * De kop van dit onderdeel, of een verse met de standaardtekst erin.
     *
     * Niet opgeslagen als hij nieuw is: dit wordt ook aangeroepen bij het
     * tonen van de publieke site, en een GET hoort niets weg te schrijven.
     * De seeder legt de echte rij aan.
     */
    public static function voor(PageSectionKey $sectie): self
    {
        return self::query()->where('section', $sectie)->first()
            ?? new self(['section' => $sectie, ...self::standaard($sectie)]);
    }

    /**
     * De tekst waarmee een onderdeel begint.
     *
     * **Eén bron, en dat is een verbetering.** In de oude opzet stond
     * deze tekst twee keer -- één keer in het model als vangnet voor een
     * vergeten seeder, één keer in de seeder om de rij echt neer te
     * zetten -- met een commentaar dat dat met opzet dubbel was. Met één
     * model kan het beter: `SectionHeadingSeeder` haalt zijn tekst hier
     * op, dus de twee kunnen niet meer uit elkaar lopen.
     *
     * Het is **een startpunt en geen definitieve tekst**. De klant
     * schrijft hem om zodra hij weet wat er moet staan; daarvoor bestaat
     * het beheerscherm.
     *
     * Een onderdeel dat hier niet in staat krijgt zijn eigen sleutel als
     * titel. Dat is lelijk en dat is de bedoeling: het valt op, zodat
     * iemand hem gaat schrijven.
     *
     * **Geen `__()` om deze tekst heen**, en dat is geen vergeten
     * vertaling. Dit zijn de waarden van de kolommen `_nl` en `_en`, en
     * die staan allebei vast. Zou het Nederlandse veld door de
     * vertaalfunctie gaan, dan komt er bij een Engelse bezoeker Engelse
     * tekst in het Nederlandse veld terecht -- en die schrijft hij dan
     * over in zijn beheerscherm.
     *
     * @return array<string, string|null>
     */
    public static function standaard(PageSectionKey $sectie): array
    {
        return match ($sectie) {
            PageSectionKey::Hero => [
                'eyebrow_nl' => 'IT-advies en realisatie',
                'eyebrow_en' => 'IT advice and delivery',
                'title_nl' => 'Techniek die doet wat je bedrijf nodig heeft.',
                'title_en' => 'Technology that does what your business needs.',
                'intro_nl' => 'Van advies tot bouw en beheer. Zonder ruis, zonder afhankelijkheid van één leverancier.',
                'intro_en' => 'From advice to building and management. No noise, and no dependence on a single supplier.',
            ],

            PageSectionKey::Diensten => [
                'eyebrow_nl' => 'Diensten',
                'eyebrow_en' => 'Services',
                'title_nl' => 'Wat we doen',
                'title_en' => 'What we do',
                'intro_nl' => 'Drie dingen, en die goed. De rest besteden we liever uit dan half te doen.',
                'intro_en' => 'Three things, and done properly. We would rather outsource the rest than do it by halves.',
            ],

            /*
             * De tijdlijn heeft geen opschrift. Zijn oude tabel had de
             * kolom niet, en die van nu wel -- maar het scherm laat hem
             * niet zien en er staat er dus geen. Dit samenvoegen is een
             * verhuizing en geen herontwerp van de voorpagina.
             */
            PageSectionKey::Ervaring => [
                'title_nl' => 'Waar dit vandaan komt',
                'title_en' => 'Where this comes from',
                'intro_nl' => 'De weg ernaartoe, van nu naar toen.',
                'intro_en' => 'The road here, from now back to then.',
            ],

            PageSectionKey::Certificaten => [
                'eyebrow_nl' => 'Certificaten',
                'eyebrow_en' => 'Certifications',
                'title_nl' => 'Zwart op wit',
                'title_en' => 'In black and white',
                'intro_nl' => 'Waar ik voor getoetst ben, en door wie. Papier is geduldig, maar een examen is dat niet.',
                'intro_en' => 'What I have been tested on, and by whom. Paper is patient; an exam is not.',
            ],

            PageSectionKey::Statistieken => [
                'eyebrow_nl' => 'In cijfers',
                'eyebrow_en' => 'By the numbers',
                'title_nl' => 'Waar ik goed in ben',
                'title_en' => 'What I am good at',
                'intro_nl' => 'Geen vage beloftes maar getallen. Scroll erdoorheen en ze vullen zich.',
                'intro_en' => 'No vague promises, just numbers. Scroll through and they fill themselves in.',
            ],

            default => ['title_nl' => ucfirst($sectie->value)],
        };
    }

    /** Het opschrift boven de titel, of null. */
    public function opschrift(): ?string
    {
        $tekst = $this->engels() && filled($this->eyebrow_en)
            ? $this->eyebrow_en
            : $this->eyebrow_nl;

        return filled($tekst) ? (string) $tekst : null;
    }

    /** De titel, met terugval op het Nederlands. */
    public function titel(): string
    {
        return $this->engels() && filled($this->title_en)
            ? (string) $this->title_en
            : $this->title_nl;
    }

    /** De zin eronder, of null. Optioneel, dus zonder terugval. */
    public function inleiding(): ?string
    {
        $tekst = $this->engels() ? $this->intro_en : $this->intro_nl;

        return filled($tekst) ? (string) $tekst : null;
    }

    /**
     * De drie teksten zoals de website ze nodig heeft.
     *
     * De keuze tussen Nederlands en Engels is hier al gemaakt; het
     * beheerscherm krijgt een andere vorm, want dat bewerkt ze allebei.
     *
     * @return array<string, string|null>
     */
    public function voorDeSite(): array
    {
        return [
            'opschrift' => $this->opschrift(),
            'titel' => $this->titel(),
            'inleiding' => $this->inleiding(),
        ];
    }

    /**
     * De zes velden los, zoals het beheerscherm ze bewerkt.
     *
     * @return array<string, string|bool|null>
     */
    public function voorHetScherm(): array
    {
        return [
            'eyebrow_nl' => $this->eyebrow_nl,
            'eyebrow_en' => $this->eyebrow_en,
            'title_nl' => $this->title_nl,
            'title_en' => $this->title_en,
            'intro_nl' => $this->intro_nl,
            'intro_en' => $this->intro_en,
            'automatisch_vertaald' => $this->machine_translated_at !== null,
        ];
    }

    private function engels(): bool
    {
        return app()->getLocale() === 'en';
    }

    public static function activityName(): string
    {
        return __('Koptekst');
    }

    /**
     * Welk onderdeel het was, en niet welke tekst erin stond.
     *
     * "Koptekst — Diensten" zegt precies genoeg om de regel terug te
     * vinden. De titel zelf verandert juist bij zo'n wijziging, en dan
     * staat er in het logboek een naam die nergens meer op slaat.
     */
    public function activityLabel(): string
    {
        return $this->section->label();
    }
}
