<?php

namespace App\Enums;

/**
 * Hoe de pagina met alle projecten eruitziet.
 *
 * Twee smaken, en de keuze is van de eigenaar.
 *
 * **Alles even groot, en alleen de richting verschilt.** Onder elkaar als
 * lijst, of naast elkaar als kaarten. Hier heeft ook een derde vorm
 * gestaan -- afwisselend één brede en twee smalle -- en die is eruit
 * gehaald: hij riep steeds dezelfde vraag op, namelijk waarom die ene
 * anders was dan de rest. Het eerlijke antwoord was "omdat hij toevallig
 * op plek één van de ronde stond", en dat is geen antwoord.
 *
 * Wat wel per eigenaar verschilt: de lijst is het beste te scannen en
 * blijft compact, het raster is luchtiger en komt beter tot zijn recht
 * zodra er afbeeldingen bij de projecten staan. Wat er over een jaar in
 * staat weet hij beter dan wij, dus dit is een instelling.
 *
 * Zie docs/architecture/modules/projecten.md.
 */
enum ProjectWeergave: string
{
    case Lijst = 'lijst';
    case Raster = 'raster';

    public function label(): string
    {
        return __($this->sleutel());
    }

    public function sleutel(): string
    {
        return match ($this) {
            self::Lijst => 'Onder elkaar als lijst',
            self::Raster => 'Naast elkaar als kaarten',
        };
    }

    public function omschrijving(): string
    {
        return match ($this) {
            self::Lijst => __('Elk project een eigen regel onder elkaar, allemaal even groot en compact. Het makkelijkst te scannen: je leest de titels van boven naar beneden en klikt door op wat je wil zien.'),
            self::Raster => __('Dezelfde projecten als kaarten naast elkaar. Luchtiger, en fijner zodra je projecten een afbeelding hebben.'),
        };
    }

    /**
     * De twee keuzes voor het instellingenvenster.
     *
     * @return array<int, array<string, string>>
     */
    public static function opties(): array
    {
        return array_map(fn (self $weergave) => [
            'value' => $weergave->value,
            'label' => $weergave->label(),
            'omschrijving' => $weergave->omschrijving(),
        ], self::cases());
    }
}
