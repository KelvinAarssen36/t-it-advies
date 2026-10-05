<?php

namespace App\Enums;

/**
 * Hoe de mail van deze website eruitziet.
 *
 * **Twee stijlen en niet meer.** Een keuzelijst met vijf varianten is een
 * ontwerpopdracht die bij de klant op het bord belandt; dit is een keuze
 * tussen twee dingen die allebei af zijn.
 *
 * | Stijl       | Wat het is                                              |
 * | ----------- | ------------------------------------------------------- |
 * | `licht`     | Witte mail met de merkkleur als accent                  |
 * | `huisstijl` | Midnight Navy, zoals de website zelf                    |
 *
 * **`licht` is de standaard, en dat is geen smaak.** Een donkere mail is
 * mooier maar riskanter: oudere Outlooks op Windows negeren
 * achtergrondkleuren op sommige elementen, en een postvak dat zelf een
 * donkere modus forceert kan de tekst omzetten. Een lichte mail komt
 * overal goed aan. Wie de huisstijl wil kiest hem bewust -- en kan hem
 * net zo makkelijk terugzetten.
 *
 * Zie docs/architecture/mail-en-queues.md.
 */
enum MailStijl: string
{
    case Licht = 'licht';
    case Huisstijl = 'huisstijl';

    /** Hoe het heet op het scherm Weergave. */
    public function label(): string
    {
        return match ($this) {
            self::Licht => __('Licht'),
            self::Huisstijl => __('Huisstijl'),
        };
    }

    /** Eén regel die zegt wat je krijgt. */
    public function omschrijving(): string
    {
        return match ($this) {
            self::Licht => __('Een witte mail met je merkkleur als accent. Komt in elk mailprogramma goed aan.'),
            self::Huisstijl => __('Donkerblauw, zoals je website. Mooier, en in een enkel ouder mailprogramma kan de achtergrond wegvallen.'),
        };
    }

    /**
     * De naam van het thema in `resources/views/vendor/mail/html/themes`.
     *
     * Laravel zoekt het CSS-bestand op deze naam en voegt het regel voor
     * regel in de tags van de mail. Zie `Illuminate\Mail\Markdown`.
     */
    public function thema(): string
    {
        return match ($this) {
            self::Licht => 'atit',
            self::Huisstijl => 'atit-huisstijl',
        };
    }

    /**
     * Of deze stijl een donkere achtergrond heeft.
     *
     * De kop van de mail gebruikt dit: op donker hoort de voettekst lichter
     * en het logo heeft meer lucht nodig. Zie header.blade.php.
     */
    public function donker(): bool
    {
        return $this === self::Huisstijl;
    }

    /**
     * De stijl die bij een themanaam hoort.
     *
     * Dit is `thema()` de andere kant op, en het bestaat voor één plek: de
     * maillayout. Die krijgt geen stijl mee -- hij wordt gerenderd door
     * Laravel zelf -- maar kan wel bij het thema dat op dat moment aan de
     * hand is, via `app(Markdown::class)->getTheme()`. Zie
     * resources/views/vendor/mail/html/layout.blade.php.
     *
     * Een naam die we niet kennen levert `Licht`. Dat is hier de veilige
     * uitkomst: de lichte mail komt overal goed aan, en een onbekend thema
     * donker noemen zou lichte tekst op wit kunnen opleveren.
     */
    public static function vanThema(?string $thema): self
    {
        foreach (self::cases() as $stijl) {
            if ($stijl->thema() === $thema) {
                return $stijl;
            }
        }

        return self::Licht;
    }

    /**
     * De twee stijlen voor een keuzelijst in het portaal.
     *
     * @return array<int, array<string, string>>
     */
    public static function opties(): array
    {
        return array_map(
            fn (self $stijl) => [
                'value' => $stijl->value,
                'label' => $stijl->label(),
                'omschrijving' => $stijl->omschrijving(),
            ],
            self::cases(),
        );
    }
}
