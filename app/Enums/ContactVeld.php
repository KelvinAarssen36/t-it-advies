<?php

namespace App\Enums;

/**
 * De velden die het contactformulier kán hebben.
 *
 * **Dit is de enige lijst.** De database bewaart alleen de stand en de
 * volgorde; wát een veld is staat hier. Dezelfde verdeling als bij
 * `PageSectionKey`, en om dezelfde reden: een veld dat de eigenaar zelf zou
 * aanmaken heeft geen invoertype, geen validatieregel, geen plek in de mail
 * en geen kolom in het overzicht. Dat zijn vier dingen die niet uit een
 * tekstveld kunnen komen.
 *
 * Wil hij een nieuw soort veld, dan is dat werk voor ons: een case erbij,
 * een kolom erbij en een regel in de seeder. De volgorde en de stand zijn
 * van hem.
 *
 * **Het label staat hier en niet in de database.** Daardoor is het gratis
 * tweetalig en klopt het ook nog bij een aanvraag van een jaar oud. Zou de
 * eigenaar het zelf kunnen typen, dan moet hij er Engels bij onderhouden en
 * staat er bij een leeg Engels label een half-Nederlands formulier.
 *
 * Zie docs/architecture/modules/contact.md.
 */
enum ContactVeld: string
{
    case Naam = 'name';
    case Email = 'email';
    case Bedrijf = 'company';
    case Telefoon = 'phone';
    case Onderwerp = 'subject';
    case Bericht = 'message';

    /**
     * Of dit veld altijd zichtbaar én altijd verplicht is.
     *
     * **Zonder naam, adres en bericht kun je niemand antwoorden** -- dan is
     * het formulier geen contactformulier meer. Dat staat hier en niet in
     * de database, zodat het niet met een aangepast verzoek of een losse
     * regel in de tabel om te zetten is. Zelfde gedachte als
     * `PageSectionKey::vast()` voor de kop en de voettekst.
     */
    public function vast(): bool
    {
        return match ($this) {
            self::Naam, self::Email, self::Bericht => true,
            default => false,
        };
    }

    /** Hoe het veld op het formulier heet. Met hoofdletter. */
    public function label(): string
    {
        return __($this->sleutel());
    }

    /** De Nederlandse tekst van het label, onvertaald. */
    public function sleutel(): string
    {
        return match ($this) {
            self::Naam => 'Naam',
            self::Email => 'E-mailadres',
            self::Bedrijf => 'Bedrijfsnaam',
            self::Telefoon => 'Telefoonnummer',
            self::Onderwerp => 'Onderwerp',
            self::Bericht => 'Bericht',
        };
    }

    /**
     * Hoe het veld in een foutmelding heet. Zonder hoofdletter.
     *
     * Twee methoden en geen `Str::lower()`: in dit project is een los
     * Nederlands woord tegelijk de vertaalsleutel, en "E-mailadres" en
     * "e-mailadres" zijn twee verschillende sleutels met een eigen Engelse
     * tekst. Zie docs/architecture/vertalingen.md.
     */
    public function attribuut(): string
    {
        return match ($this) {
            self::Naam => __('naam'),
            self::Email => __('e-mailadres'),
            self::Bedrijf => __('bedrijfsnaam'),
            self::Telefoon => __('telefoonnummer'),
            self::Onderwerp => __('onderwerp'),
            self::Bericht => __('bericht'),
        };
    }

    public function type(): ContactVeldType
    {
        return match ($this) {
            self::Email => ContactVeldType::Email,
            self::Telefoon => ContactVeldType::Telefoon,
            self::Onderwerp => ContactVeldType::Onderwerp,
            self::Bericht => ContactVeldType::Tekstvak,
            default => ContactVeldType::Tekst,
        };
    }

    /**
     * De regels van dit veld, zónder `required` of `nullable`.
     *
     * Of het veld moet staat in de database en wordt er in
     * `Contactformulier::regels()` vóór geplakt. Zo staat de inhoudelijke
     * regel op één plek en de instelling op een andere, en kunnen die twee
     * niet door elkaar gaan lopen.
     *
     * @return array<int, mixed>
     */
    public function regels(): array
    {
        return match ($this) {
            self::Naam => ['string', 'max:'.$this->maximum()],
            self::Email => ['string', self::emailRegel(), 'max:'.$this->maximum()],
            self::Bedrijf => ['string', 'max:'.$this->maximum()],

            /*
             * Geen strenge nummercontrole. "06 12 34 56 78" en
             * "+31 (0)6-12345678" zijn allebei wat mensen intypen, en een
             * nummer dat wij weigeren is een aanvraag die we niet krijgen.
             * Wat hier wordt tegengehouden is een veld vol letters, en dat
             * is genoeg.
             */
            self::Telefoon => ['string', 'max:'.$this->maximum(), 'regex:/^[\d\s+()\/.\-]{6,32}$/'],

            self::Onderwerp => ['string', 'max:'.$this->maximum()],
            self::Bericht => ['string', 'min:10', 'max:'.$this->maximum()],
        };
    }

    /** De maximale lengte, ook gebruikt door het formulier zelf. */
    public function maximum(): int
    {
        return match ($this) {
            self::Naam, self::Bedrijf => 120,
            self::Email => 190,
            self::Telefoon => 32,
            self::Onderwerp => 160,
            self::Bericht => 5000,
        };
    }

    /** Wat de browser mag voorinvullen. */
    public function autocomplete(): ?string
    {
        return match ($this) {
            self::Naam => 'name',
            self::Email => 'email',
            self::Bedrijf => 'organization',
            self::Telefoon => 'tel',
            default => null,
        };
    }

    /** De volgorde waarin de velden standaard op het formulier staan. */
    public function standaardPositie(): int
    {
        return match ($this) {
            self::Naam => 1,
            self::Email => 2,
            self::Bedrijf => 3,
            self::Telefoon => 4,
            self::Onderwerp => 5,
            self::Bericht => 6,
        };
    }

    /** De stand waarmee een veld begint als het er nog niet was. */
    public function standaardStatus(): ContactVeldStatus
    {
        if ($this->vast()) {
            return ContactVeldStatus::Verplicht;
        }

        return match ($this) {
            // Het onderwerp stond er altijd al en was verplicht; dat blijft
            // zo, zodat deze module niets verandert aan wat er nu staat.
            self::Onderwerp => ContactVeldStatus::Verplicht,

            // Bedrijfsnaam en telefoonnummer zijn nieuw. Ze staan uit, want
            // een formulier dat opeens meer vraagt is een formulier dat
            // minder wordt ingevuld -- dat is zijn keuze, niet de onze.
            default => ContactVeldStatus::Uit,
        };
    }

    /**
     * De e-mailregel, met dezelfde uitzondering als het oude formulier.
     *
     * `dns` slaat een echte DNS-opvraging op elke test, en dat maakt de
     * testsuite traag en afhankelijk van een netwerk.
     */
    private static function emailRegel(): string
    {
        return app()->runningUnitTests() ? 'email:rfc' : 'email:rfc,dns';
    }
}
