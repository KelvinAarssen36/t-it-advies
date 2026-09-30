<?php

namespace App\Http\Requests\Website\Concerns;

use App\Support\Media\Uitsnede;
use Illuminate\Http\UploadedFile;

/**
 * De velden van een logo dat de klant uploadt.
 *
 * Dit stond volledig in `ExperienceRequest`, en de certificaten hebben
 * precies hetzelfde nodig: één afbeelding, met een uitsnijvenster
 * ervoor. Zeventig regels validatie op twee plekken is zeventig regels
 * die uit elkaar gaan lopen -- en dan accepteert het ene scherm een
 * bestand dat het andere weigert.
 *
 * Zie docs/architecture/formulieren-en-schuifbalken.md voor wat de
 * browser er vooraf al mee doet, en config/media.php voor de grenzen.
 */
trait LogoVelden
{
    /**
     * De regels voor het bestand en het uitsnijvenster.
     *
     * Drie grenzen op het bestand, elk met een eigen reden:
     *
     * - `image` en `mimes` samen: `image` kijkt naar de inhoud van het
     *   bestand, `mimes` naar de extensie. Allebei, want de eerste houdt
     *   een hernoemd script tegen en de tweede houdt formaten buiten de
     *   deur die wel een afbeelding zijn maar niet in elke browser
     *   werken.
     * - `max`: meer heeft een logo nooit nodig, en het scheelt de klant
     *   een upload die op een trage verbinding afbreekt.
     * - `dimensions` met een maximum: GD zet een afbeelding uitgepakt in
     *   het geheugen, vier bytes per beeldpunt. Een plaatje van 20.000
     *   bij 20.000 past binnen twee megabyte op schijf maar vraagt ruim
     *   een gigabyte werkgeheugen. Zonder deze grens is dat een manier
     *   om de server om te duwen.
     *
     * De getallen staan in config/media.php, want ze moeten ook kloppen
     * met wat de browser vooraf doet en met wat de hostingomgeving
     * toestaat. Eén bron dus, en niet drie.
     *
     * @return array<string, array<int, mixed>>
     */
    protected function logoRegels(): array
    {
        return [
            'logo' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.$this->maxKilobytes(),
                sprintf(
                    'dimensions:min_width=%1$d,min_height=%1$d,max_width=%2$d,max_height=%2$d',
                    $this->minZijde(),
                    $this->maxZijde(),
                ),
            ],

            /** Het bestaande logo weghalen zonder er een nieuw voor terug. */
            'logo_verwijderen' => ['boolean'],

            /*
             * Hoe de klant het beeld in het vakje heeft gezet. De grenzen
             * staan hier én in App\Support\Media\Uitsnede: die laatste is
             * de laatste halte voordat GD ermee gaat rekenen, en een zoom
             * van nul levert daar geen foutmelding op maar een onzinnig
             * plaatje.
             */
            'logo_zoom' => ['nullable', 'numeric', 'min:1', 'max:5'],
            'logo_x' => ['nullable', 'numeric', 'min:-1', 'max:1'],
            'logo_y' => ['nullable', 'numeric', 'min:-1', 'max:1'],
            'logo_plaat' => ['boolean'],
        ];
    }

    /**
     * De meldingen die zonder toelichting onbegrijpelijk zijn.
     *
     * @return array<string, string>
     */
    protected function logoBerichten(): array
    {
        return [
            'logo.dimensions' => __(
                'Dit plaatje is te klein of te groot. Gebruik een afbeelding van minstens :min en hoogstens :max pixels.',
                ['min' => $this->minZijde(), 'max' => $this->maxZijde()],
            ),
            'logo.max' => __(
                'Het logo mag hoogstens :aantal MB zijn.',
                ['aantal' => round($this->maxKilobytes() / 1024, 1)],
            ),
        ];
    }

    /** Het geüploade logo, of null als er geen nieuw bestand meekwam. */
    public function logo(): ?UploadedFile
    {
        $bestand = $this->file('logo');

        return $bestand instanceof UploadedFile ? $bestand : null;
    }

    /**
     * Hoe de klant het beeld in het vakje heeft gezet.
     *
     * Kwamen er geen waarden mee, dan valt het terug op "het hele beeld
     * passend, gecentreerd, op wit". Dat is dezelfde stand waarmee de
     * kiezer in het formulier begint.
     */
    public function uitsnede(): Uitsnede
    {
        return new Uitsnede(
            (float) ($this->input('logo_zoom') ?? 1.0),
            (float) ($this->input('logo_x') ?? 0.0),
            (float) ($this->input('logo_y') ?? 0.0),
            $this->boolean('logo_plaat', true),
        );
    }

    /**
     * Wil de klant het bestaande logo weg?
     *
     * Dit is iets anders dan "er kwam geen bestand mee". Bij elke opslag
     * zonder nieuw bestand blijft het oude staan -- anders raak je je
     * logo kwijt zodra je een typefout verbetert. Weghalen moet je dus
     * expliciet vragen.
     */
    public function wilLogoWeg(): bool
    {
        return $this->boolean('logo_verwijderen');
    }

    /** De grootste upload die we accepteren, in kilobytes. */
    private function maxKilobytes(): int
    {
        return (int) config('media.logo.max_kb', 2048);
    }

    /** De kortste zijde die nog bruikbaar is. */
    private function minZijde(): int
    {
        return (int) config('media.logo.min_zijde', 48);
    }

    /** De langste zijde die we aan GD durven te geven. */
    private function maxZijde(): int
    {
        return (int) config('media.logo.max_zijde', 3000);
    }
}
