<?php

namespace App\Enums;

/**
 * Het pictogram op een dienstkaart.
 *
 * Een vaste set en geen upload, om dezelfde redenen als bij
 * ExperienceIcon: een plaatje van veertig pixels is opslag, validatie en
 * opruimwerk niet waard, en aangeleverd beeld komt in willekeurige
 * kleuren binnen waarvan de helft wegvalt op een donkere achtergrond.
 * Met deze set staat er altijd iets dat in de huisstijl past.
 *
 * De sleutels zijn bewust beschrijvend en niet de naam van een pictogram
 * uit de bibliotheek: wisselen we ooit van iconenset, dan hoeft de
 * database niet mee. De koppeling naar het echte pictogram staat in
 * resources/js/components/site/DienstIcoon.vue.
 *
 * **Een aparte set naast ExperienceIcon en niet één gedeelde.** Een
 * ervaring vraagt om pictogrammen die een soort functie uitbeelden
 * (opleiding, team, eigen bedrijf); een dienst om pictogrammen die een
 * vakgebied uitbeelden (cloud, netwerk, data). Eén lijst met allebei
 * erin maakt de keuzelijst twee keer zo lang en half onbruikbaar.
 *
 * Zie docs/architecture/modules/diensten.md.
 */
enum ServiceIcon: string
{
    case Advice = 'advies';
    case Delivery = 'realisatie';
    case Maintenance = 'beheer';
    case Network = 'netwerk';
    case Security = 'beveiliging';
    case Cloud = 'cloud';
    case Workplace = 'werkplek';
    case Data = 'data';

    /** Hoe het pictogram heet in de keuzelijst. */
    public function label(): string
    {
        return match ($this) {
            self::Advice => __('Advies'),
            self::Delivery => __('Realisatie'),
            /*
             * "Onderhoud" en niet "Beheer", net als bij ExperienceIcon:
             * dat tweede is in dit portaal de naam van een menugroep, en
             * één Nederlands woord met twee betekenissen levert in het
             * Engels één vertaling op voor allebei.
             */
            self::Maintenance => __('Onderhoud'),
            self::Network => __('Netwerk'),
            self::Security => __('Beveiliging'),
            self::Cloud => __('Cloud'),
            self::Workplace => __('Werkplek'),
            self::Data => __('Data'),
        };
    }

    /**
     * De keuzes voor de keuzelijst in het beheerscherm.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function opties(): array
    {
        return array_map(
            fn (self $pictogram) => [
                'value' => $pictogram->value,
                'label' => $pictogram->label(),
            ],
            self::cases(),
        );
    }
}
