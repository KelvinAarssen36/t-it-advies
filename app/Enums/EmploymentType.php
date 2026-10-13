<?php

namespace App\Enums;

/**
 * Het soort dienstverband bij een ervaring.
 *
 * Dit is een **centraal vertaald** veld en geen tekst die de klant per item
 * invult. Dat scheelt hem werk -- hij typt "Fulltime" niet vijf keer en
 * daarna nog eens vijf keer in het Engels -- en het houdt het Engels
 * consistent. Een vrij tekstveld zou hier "fulltime", "Full-time" en
 * "40 uur" naast elkaar opleveren.
 *
 * De waarden volgen die van LinkedIn, want daar komen de gegevens vandaan.
 * "Vaste aanstelling" en "Tijdelijk contract" staan er los in naast
 * fulltime en parttime: dat zijn twee verschillende vragen -- hoeveel uur,
 * en voor hoe lang -- en de loopbaan van de eigenaar bevat allebei.
 *
 * Zie docs/architecture/modules/ervaring.md.
 */
enum EmploymentType: string
{
    case FullTime = 'fulltime';
    case PartTime = 'parttime';
    case Freelance = 'freelance';
    case Permanent = 'vast';
    case Temporary = 'tijdelijk';
    case Internship = 'stage';
    case SelfEmployed = 'zelfstandig';

    public function label(): string
    {
        return match ($this) {
            self::FullTime => __('Fulltime'),
            self::PartTime => __('Parttime'),
            self::Freelance => __('Freelance'),
            self::Permanent => __('Vaste aanstelling'),
            self::Temporary => __('Tijdelijk contract'),
            self::Internship => __('Stage'),
            self::SelfEmployed => __('Zelfstandig'),
        };
    }

    /**
     * Alle waarden als keuzelijst voor het formulier.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public static function opties(): array
    {
        return array_map(
            fn (self $soort) => ['value' => $soort->value, 'label' => $soort->label()],
            self::cases(),
        );
    }
}
