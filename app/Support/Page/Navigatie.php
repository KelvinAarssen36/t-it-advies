<?php

namespace App\Support\Page;

use App\Enums\PageSectionKey;
use App\Models\PageSection;

/**
 * Het menu van de publieke site: welke onderdelen erop staan, op volgorde.
 *
 * **Dit stond in `HomeController` en moest eruit, want de subpagina's
 * hebben het ook nodig.** Op `/privacy`, `/contact` en `/over-mij` werd de
 * hele navigatiebalk vervangen door één terugknop, omdat de lijst daar leeg
 * was. Dat is de oorzaak van alle dubbele terugknoppen geweest: de balk was
 * leeg, dus er moest iets in, en de pagina zelf zette er ook nog iets neer.
 *
 * Nu staat de lijst op één plek en sturen alle publieke pagina's hem mee.
 * Op de voorpagina zijn het ankers; op een subpagina worden het links naar
 * de voorpagina bij dat onderdeel. De navigatie is daarmee zelf de weg
 * terug, en dat is één klik naar het onderdeel dat je wilde in plaats van
 * naar de bovenkant.
 *
 * **Niet in de gedeelde props van Inertia.** Dat zou de makkelijkste weg
 * zijn, maar dan doet élk portaalverzoek deze query plus alle
 * inhoudstellers voor een menu dat daar niet bestaat. Drie controllers die
 * één methode aanroepen is minder slim en meer in orde.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class Navigatie
{
    public function __construct(private readonly SectionContent $inhoud) {}

    /**
     * De sleutels van de onderdelen die op de voorpagina staan.
     *
     * Is er nog nooit geseed, dan valt het terug op de volgorde uit de
     * code. Anders levert een vergeten `db:seed` een website op die alleen
     * nog uit een kop en een voettekst bestaat -- en dat is precies het
     * soort fout dat je op de publieke site niet wilt laten afhangen van of
     * iemand eraan gedacht heeft.
     *
     * @return array<int, string>
     */
    public function sleutels(): array
    {
        $rijen = PageSection::query()->aangezet()->opVolgorde()->get();

        $sleutels = $rijen->isEmpty()
            ? PageSectionKey::verplaatsbaar()
            : $rijen->map(fn (PageSection $rij) => $rij->key)->all();

        return array_values(array_map(
            fn (PageSectionKey $sectie) => $sectie->value,
            array_filter(
                $sleutels,
                fn (PageSectionKey $sectie) => ! $sectie->vast() && $this->inhoud->gevuld($sectie),
            ),
        ));
    }

    /**
     * Het menu zoals de kop het nodig heeft.
     *
     * De labels komen van de server en niet uit een tabel in de frontend,
     * want ze zijn vertaald. Zie PageSectionKey::label().
     *
     * @param  array<int, string>|null  $sleutels  De al bepaalde lijst, als
     *                                             de aanroeper hem toch al
     *                                             had -- de voorpagina
     *                                             gebruikt hem ook voor de
     *                                             secties zelf.
     * @return array<int, array{key: string, label: string}>
     */
    public function menu(?array $sleutels = null): array
    {
        return array_map(
            fn (string $sleutel) => [
                'key' => $sleutel,
                'label' => PageSectionKey::from($sleutel)->label(),
            ],
            $sleutels ?? $this->sleutels(),
        );
    }
}
