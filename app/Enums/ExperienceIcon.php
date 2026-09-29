<?php

namespace App\Enums;

/**
 * Het pictogram naast een ervaring op de tijdlijn.
 *
 * **Waarom een vaste set en geen logo dat de klant uploadt.** Een upload
 * betekent opslag, validatie, opruimen bij verwijderen en een antwoord op
 * de back-upvraag die nog openstaat -- en dat alles voor een plaatje van
 * veertig pixels. Aangeleverde logo's komen bovendien in willekeurige
 * kleuren en kwaliteit binnen, en op de donkere tijdlijn valt de helft
 * daarvan weg. Met deze set staat er altijd iets dat in de huisstijl past.
 *
 * Komt er later toch behoefte aan echte logo's, dan is dat een eigen
 * beslissing met een eigen regel in het beslislogboek.
 *
 * De sleutels zijn bewust beschrijvend en niet de naam van een pictogram
 * uit de bibliotheek: wisselen we ooit van iconenset, dan hoeft de database
 * niet mee. De koppeling naar het echte pictogram staat in
 * resources/js/components/site/ErvaringIcoon.vue.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
enum ExperienceIcon: string
{
    case Work = 'werk';
    case Company = 'bedrijf';
    case Development = 'ontwikkeling';
    case Infrastructure = 'infrastructuur';
    case Maintenance = 'beheer';
    case Security = 'beveiliging';
    case Advice = 'advies';
    case Education = 'opleiding';
    case Team = 'team';
    case Venture = 'eigen-bedrijf';

    public function label(): string
    {
        return match ($this) {
            self::Work => __('Werk'),
            self::Company => __('Bedrijf'),
            self::Development => __('Ontwikkeling'),
            self::Infrastructure => __('Infrastructuur'),
            self::Maintenance => __('Beheer'),
            self::Security => __('Beveiliging'),
            self::Advice => __('Advies'),
            self::Education => __('Opleiding'),
            self::Team => __('Team'),
            self::Venture => __('Eigen bedrijf'),
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function opties(): array
    {
        return array_map(
            fn (self $icoon) => ['value' => $icoon->value, 'label' => $icoon->label()],
            self::cases(),
        );
    }
}
