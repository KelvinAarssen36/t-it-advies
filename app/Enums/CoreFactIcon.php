<?php

namespace App\Enums;

/**
 * Het soort kerngegeven, en daarmee het pictogram ervoor.
 *
 * Een vaste set en geen upload, om dezelfde redenen als bij `ServiceIcon`
 * en `ExperienceIcon`: een plaatje van veertig pixels is opslag,
 * validatie en opruimwerk niet waard, en aangeleverd beeld komt in
 * willekeurige kleuren binnen waarvan de helft wegvalt op een donkere
 * achtergrond.
 *
 * **Deze set is ook de inhoudsopgave van de module.** Bij een dienst kiest
 * de klant een plaatje bij tekst die hij zelf verzint; hier zeggen de acht
 * soorten wát er in een kerngegeven thuishoort. Dat is met opzet: hij
 * vroeg ons te bedenken wat erin moet, en dit is waar dat antwoord staat.
 * Het beheerscherm stelt op grond hiervan ook het label voor.
 *
 * De sleutels zijn beschrijvend en niet de naam van een pictogram uit de
 * bibliotheek: wisselen we ooit van iconenset, dan hoeft de database niet
 * mee. De koppeling naar het echte pictogram staat in
 * resources/js/components/site/KerngegevenIcoon.vue.
 *
 * Wil de klant een negende soort, dan voegen wij een case toe -- dezelfde
 * afspraak als bij de andere modules.
 *
 * Zie docs/architecture/modules/kerngegevens.md.
 */
enum CoreFactIcon: string
{
    case Availability = 'beschikbaarheid';
    case Area = 'werkgebied';
    case Workstyle = 'werkvorm';
    case Response = 'reactietijd';
    case Languages = 'talen';
    case Cooperation = 'samenwerking';
    case Company = 'bedrijf';
    case Terms = 'voorwaarden';

    /** Hoe het soort heet in de keuzelijst. */
    public function label(): string
    {
        return match ($this) {
            self::Availability => __('Beschikbaarheid'),
            self::Area => __('Werkgebied'),
            self::Workstyle => __('Werkvorm'),
            self::Response => __('Reactietijd'),
            self::Languages => __('Talen'),
            self::Cooperation => __('Samenwerking'),
            self::Company => __('Bedrijfsgegevens'),
            self::Terms => __('Voorwaarden'),
        };
    }

    /**
     * Een voorbeeld van wat er bij dit soort hoort.
     *
     * Staat op het lege beheerscherm, zodat de eigenaar weet wat er wordt
     * bedoeld. **Het is nadrukkelijk een voorbeeld en geen standaardwaarde
     * die wordt opgeslagen**: zou "KvK 12345678" ooit geseed worden, dan
     * staat er een onwaarheid op zijn live site tot hij hem toevallig
     * opmerkt.
     */
    public function voorbeeld(): string
    {
        return match ($this) {
            self::Availability => __('Vanaf januari, 2 tot 3 dagen per week'),
            self::Area => __('Noord-Brabant, landelijk in overleg'),
            self::Workstyle => __('Op locatie, op afstand of allebei'),
            self::Response => __('Binnen één werkdag'),
            self::Languages => __('Nederlands en Engels'),
            self::Cooperation => __('Interim, project of los advies'),
            self::Company => __('KvK en btw-nummer'),
            self::Terms => __('Eigen voorwaarden, verzekerd'),
        };
    }

    /**
     * De keuzes voor de keuzelijst in het beheerscherm.
     *
     * @return array<int, array{value: string, label: string, voorbeeld: string}>
     */
    public static function opties(): array
    {
        return array_map(
            fn (self $soort) => [
                'value' => $soort->value,
                'label' => $soort->label(),
                'voorbeeld' => $soort->voorbeeld(),
            ],
            self::cases(),
        );
    }
}
