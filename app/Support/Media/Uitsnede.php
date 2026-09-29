<?php

namespace App\Support\Media;

/**
 * Hoe een geüpload beeld in het ronde vakje komt te staan.
 *
 * **Waarom de klant dit zelf bepaalt.** Wat er binnenkomt is niet te
 * voorspellen: een liggend logo met de bedrijfsnaam ernaast, een vierkant
 * beeldmerk met veel lucht eromheen, of gewoon een foto. Elke automatische
 * regel gaat bij een van die drie mis -- bijsnijden knipt de naam eraf,
 * passend maken laat een foto met witranden staan. Wie het beeld voor zich
 * ziet, kiest in twee seconden wat wij niet kunnen raden.
 *
 * De drie getallen beschrijven precies hetzelfde als wat de klant in het
 * formulier ziet:
 *
 * | Waarde  | Betekenis                                                        |
 * | ------- | ---------------------------------------------------------------- |
 * | `zoom`  | 1 is het hele beeld passend in het vierkant; hoger snijdt bij.   |
 * | `x`, `y`| Verschuiving vanaf het midden, in halve vierkanten (-1 tot 1).   |
 * | `plaat` | Een witte ondergrond onder het beeld.                            |
 *
 * **`plaat` bakt de achtergrond in het bestand.** Dat scheelt de website
 * een uitzondering: elk opgeslagen logo is daarna een gewoon vierkant
 * plaatje dat je overal hetzelfde kunt tonen. Zonder die keuze zou een
 * donker beeldmerk met doorzichtige achtergrond op de donkere site
 * verdwijnen, en zou elk scherm dat apart moeten opvangen.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
final class Uitsnede
{
    public readonly float $zoom;

    public readonly float $x;

    public readonly float $y;

    public function __construct(
        float $zoom = 1.0,
        float $x = 0.0,
        float $y = 0.0,
        public readonly bool $plaat = true,
    ) {
        /*
         * Begrenzen gebeurt hier en niet alleen in de validatie. Het
         * formulier houdt zich er al aan, maar dit is de laatste plek waar
         * de getallen langs komen voordat GD ermee gaat rekenen -- en een
         * zoom van nul of min tien levert daar geen foutmelding op maar
         * een onzinnig plaatje.
         */
        $this->zoom = max(1.0, min(5.0, $zoom));
        $this->x = max(-1.0, min(1.0, $x));
        $this->y = max(-1.0, min(1.0, $y));
    }

    /** De stand waarin het hele beeld past, gecentreerd, op wit. */
    public static function standaard(): self
    {
        return new self;
    }
}
