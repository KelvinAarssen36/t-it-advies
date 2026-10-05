<?php

namespace App\Enums;

/**
 * Of een veld op het contactformulier staat, en zo ja of het moet.
 *
 * **Eén waarde en niet twee schuifjes.** "Zichtbaar" en "verplicht" zijn
 * twee booleans die samen vier toestanden coderen, waarvan er één onzin is:
 * onzichtbaar én verplicht. Die toestand ontstaat vanzelf -- de eigenaar
 * zet Telefoonnummer op verplicht, bedenkt zich, en zet alleen de
 * zichtbaarheid uit. Dan staat er een formulier dat niet te versturen is om
 * een veld dat er niet staat, en er is niets op het scherm dat uitlegt
 * waarom.
 *
 * Met één driestandskeuze bestaat die toestand niet. Dat scheelt ook een
 * verdedigende controle op elke plek waar de velden worden opgebouwd.
 *
 * Zie docs/architecture/modules/contact.md.
 */
enum ContactVeldStatus: string
{
    case Uit = 'uit';
    case Optioneel = 'optioneel';
    case Verplicht = 'verplicht';

    /** Staat dit veld op het formulier? */
    public function zichtbaar(): bool
    {
        return $this !== self::Uit;
    }

    /** Moet de bezoeker het invullen? */
    public function verplicht(): bool
    {
        return $this === self::Verplicht;
    }

    public function label(): string
    {
        return __($this->sleutel());
    }

    /**
     * De Nederlandse tekst, onvertaald.
     *
     * Nodig waar de taal niet die van het portaal is; zie
     * `SecurityEventType::sleutel()` voor dezelfde constructie.
     */
    public function sleutel(): string
    {
        return match ($this) {
            self::Uit => 'Staat uit',
            self::Optioneel => 'Mag leeg blijven',
            self::Verplicht => 'Moet ingevuld',
        };
    }

    /**
     * De drie keuzes voor het beheerscherm.
     *
     * @return array<int, array<string, string>>
     */
    public static function opties(): array
    {
        return array_map(fn (self $status) => [
            'value' => $status->value,
            'label' => $status->label(),
        ], self::cases());
    }
}
