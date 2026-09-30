<?php

namespace App\Enums;

/**
 * De onderdelen waaruit de landingspagina is opgebouwd.
 *
 * Dit is de enige lijst. De database bewaart alleen de **volgorde** en of
 * een onderdeel aan staat; wát een onderdeel is, staat hier. Dat is met
 * opzet zo verdeeld: een sectie die de klant zelf zou kunnen aanmaken heeft
 * geen Vue-component om te tonen, dus een vrij invulbare lijst zou alleen
 * maar lege plekken opleveren. Een nieuw onderdeel is werk voor ons, de
 * volgorde en de inhoud zijn van hem.
 *
 * **Een onderdeel toevoegen** doe je in vijf stappen; ze staan uitgeschreven
 * in docs/architecture/pagina-indeling.md. Kort:
 *
 * 1. Een `case` hier, met een label, een omschrijving en een positie.
 * 2. Een migratie die de rij toevoegt -- of gewoon `PageSectionSeeder`
 *    opnieuw draaien, die vult aan wat ontbreekt.
 * 3. Een Vue-component, en die opnemen in de kaart in Welcome.vue.
 * 4. Een teller registreren in `AppServiceProvider`, zodat het onderdeel
 *    vanzelf verdwijnt zolang de klant er nog niets in heeft gezet.
 * 5. Een regel in de zijbalk, onder Website. Elke module hoort daar zijn
 *    eigen ingang te hebben; AppSidebarTest valt om als je het vergeet.
 *
 * Zie ook App\Support\Page\SectionContent.
 */
enum PageSectionKey: string
{
    case Hero = 'hero';
    case Diensten = 'diensten';
    case Werkwijze = 'werkwijze';
    case Ervaring = 'ervaring';
    case Contact = 'contact';
    case Footer = 'footer';

    /**
     * Hoe het onderdeel heet op het indelingsscherm.
     *
     * Niet de technische sleutel: de klant leest "Kop", niet "hero".
     */
    public function label(): string
    {
        return match ($this) {
            self::Hero => __('Kop'),
            self::Diensten => __('Diensten'),
            self::Werkwijze => __('Werkwijze'),
            self::Ervaring => __('Ervaring'),
            self::Contact => __('Contact'),
            self::Footer => __('Voettekst'),
        };
    }

    /** Eén regel die zegt wat er in dit onderdeel staat. */
    public function omschrijving(): string
    {
        return match ($this) {
            self::Hero => __('Het eerste dat een bezoeker ziet: de titel, de ondertitel en de twee knoppen.'),
            self::Diensten => __('De diensten die je aanbiedt, elk met een korte toelichting en de expertise die eronder valt.'),
            self::Werkwijze => __('De stappen van kennismaken tot overdragen.'),
            self::Ervaring => __('De tijdlijn met functies en organisaties, van nu naar vroeger.'),
            self::Contact => __('Het contactformulier.'),
            self::Footer => __('De afsluiting onderaan elke pagina.'),
        };
    }

    /**
     * Of dit onderdeel op zijn plek blijft staan.
     *
     * De kop hoort bovenaan en de voettekst onderaan -- dat is niet een
     * voorkeur maar wat die twee onderdelen zíjn. Ze staan wél in de lijst,
     * want de klant hoort zijn hele pagina te zien en niet alleen het
     * middenstuk.
     *
     * Deze eigenschap staat hier en niet in de database, zodat hij niet met
     * een aangepast verzoek te veranderen is. Zie WebsiteController.
     */
    public function vast(): bool
    {
        return match ($this) {
            self::Hero, self::Footer => true,
            default => false,
        };
    }

    /**
     * De plek in de rij waarmee dit onderdeel begint.
     *
     * Alleen voor de eerste keer zaaien. Daarna is de volgorde van de klant
     * en raakt de seeder hem niet meer aan.
     *
     * De vaste onderdelen krijgen 0 en 1000. Dat er zoveel ruimte tussen
     * zit is geen reserve maar duidelijkheid: de server nummert de
     * verplaatsbare onderdelen bij elke opslag opnieuw als 1, 2, 3, en die
     * liggen daarmee altijd tussen de kop en de voettekst in.
     *
     * Overigens hangt de plek van die twee niet van dit getal af. Het
     * indelingsscherm zet ze boven- en onderaan omdat ze vast zijn, niet
     * omdat hun positie dat zegt -- een verkeerd getal in de database kan
     * de kop dus niet naar het midden verplaatsen.
     */
    public function standaardPositie(): int
    {
        return match ($this) {
            self::Hero => 0,
            self::Diensten => 1,
            self::Werkwijze => 2,
            self::Ervaring => 3,
            self::Contact => 4,
            self::Footer => 1000,
        };
    }

    /**
     * De route naar het scherm waar de inhoud van dit onderdeel wordt
     * beheerd, of null zolang dat scherm er nog niet is.
     *
     * Zolang hij null is, zet het indelingsscherm er "Nog niet te beheren"
     * bij in plaats van een link die nergens heen gaat. De onderdelen die
     * hier nog niet staan hebben hun tekst dus nog in een Vue-component.
     */
    public function beheerRoute(): ?string
    {
        return match ($this) {
            self::Hero => 'website.kop.index',
            self::Diensten => 'website.diensten.index',
            self::Ervaring => 'website.ervaring.index',
            default => null,
        };
    }

    /**
     * Alle onderdelen die de klant mag verslepen.
     *
     * @return array<int, self>
     */
    public static function verplaatsbaar(): array
    {
        return array_values(array_filter(
            self::cases(),
            fn (self $sectie) => ! $sectie->vast(),
        ));
    }
}
