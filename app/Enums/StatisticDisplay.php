<?php

namespace App\Enums;

/**
 * Hoe één statistiek op de website wordt getoond.
 *
 * **De klant kiest dit per statistiek**, en dat is het hele idee achter
 * deze module: niet wij een vaste vorm waar hij zich in moet wringen,
 * maar drie vormen waar hij per cijfer de passende bij kiest.
 *
 * | Weergave | Waarvoor                                             |
 * | -------- | ---------------------------------------------------- |
 * | Balk     | Een niveau in procenten. Het werkpaard: tien onder elkaar blijven leesbaar. |
 * | Ring     | Hetzelfde, maar als blikvanger. Drie of vier naast elkaar zijn indrukwekkend; tien zijn een dashboard. |
 * | Teller   | Alles wat géén percentage is: aantallen, jaren, bereikbaarheid. |
 *
 * De sleutels zijn Nederlands en beschrijvend, net als bij ServiceIcon:
 * ze zeggen wát het is en niet welk component het tekent. Die koppeling
 * staat in StatistiekenSection.vue.
 *
 * Zie docs/architecture/modules/statistieken.md.
 */
enum StatisticDisplay: string
{
    case Balk = 'balk';
    case Ring = 'ring';
    case Teller = 'teller';

    /** Hoe de weergave heet in de keuzelijst. */
    public function label(): string
    {
        return match ($this) {
            self::Balk => __('Balk'),
            self::Ring => __('Ring'),
            self::Teller => __('Teller'),
        };
    }

    /** Wat er in het beheerscherm bij staat. */
    public function omschrijving(): string
    {
        return match ($this) {
            self::Balk => __('Een balk die volloopt, met het percentage erachter. Het beste voor een rijtje vaardigheden onder elkaar.'),
            self::Ring => __('Een cirkel die zichzelf tekent, met het percentage in het midden. Dit is de blikvanger; gebruik hem voor je beste cijfers.'),
            self::Teller => __('Een groot getal dat oploopt. Voor alles wat geen percentage is, zoals een aantal of een jaartal.'),
        };
    }

    /**
     * Is dit een percentage?
     *
     * Een balk en een ring tekenen een deel van een geheel; dat geheel
     * is honderd. Een teller heeft geen geheel -- daar is het getal het
     * getal.
     */
    public function isPercentage(): bool
    {
        return $this !== self::Teller;
    }

    /**
     * De hoogste waarde die hier nog zin heeft.
     *
     * **Dit staat hier en niet in de migratie**, want het hangt van de
     * weergave af: een balk of ring van 340% bestaat niet, een teller
     * van 340 wel. De kolom moet allebei aankunnen, dus die is ruim; de
     * echte grens wordt in StatisticRequest afgedwongen.
     *
     * Zeven cijfers bij een teller. Daarboven leest een getal op een
     * voorpagina toch niet meer, en het past niet naast twee andere.
     */
    public function maximum(): int
    {
        return $this->isPercentage() ? 100 : 9_999_999;
    }

    /**
     * De keuzes voor de keuzelijst in het beheerscherm.
     *
     * Met de omschrijving erbij, want de keuze tussen een balk en een
     * ring is een keuze over hoe de pagina eruitziet -- en dan hoort er
     * meer te staan dan één woord.
     *
     * @return array<int, array{value: string, label: string, omschrijving: string, maximum: int}>
     */
    public static function opties(): array
    {
        return array_map(
            fn (self $weergave) => [
                'value' => $weergave->value,
                'label' => $weergave->label(),
                'omschrijving' => $weergave->omschrijving(),
                'maximum' => $weergave->maximum(),
            ],
            self::cases(),
        );
    }
}
