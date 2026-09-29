<?php

namespace App\Support\Page;

use App\Enums\PageSectionKey;

/**
 * Weet van elk onderdeel of de klant er al iets in heeft gezet.
 *
 * Dit beantwoordt één vraag, en die vraag is belangrijker dan hij lijkt:
 * **een onderdeel zonder inhoud hoort niet op de website te staan.** Een
 * kopje "Tijdlijn" met niets eronder is slordiger dan geen tijdlijn. Maar
 * hij zomaar laten verdwijnen is ook niet goed, want dan vraagt de eigenaar
 * zich af waar zijn tijdlijn is gebleven. Daarom: weg van de website, mét
 * een uitroepteken op het indelingsscherm.
 *
 * **Waarom dit een register is en geen methode op de enum.** Een module
 * telt zijn eigen inhoud -- de tijdlijn telt tijdlijnitems -- en die kennis
 * hoort bij die module en niet in een lijst die alles van iedereen weet.
 * Zo blijft het toevoegen van een module één plek bij elkaar, en kan een
 * test een teller neerzetten zonder aan de echte code te komen.
 *
 * Registreren doe je in AppServiceProvider:
 *
 *     $this->app->make(SectionContent::class)->telt(
 *         PageSectionKey::Timeline,
 *         fn () => TimelineItem::query()->count(),
 *     );
 *
 * **Zonder teller telt een onderdeel als gevuld.** Dat is met opzet de
 * veilige kant: de kop en de voettekst hebben hun tekst in de code staan en
 * zijn dus nooit leeg. Zou het andersom zijn, dan verdwijnt een onderdeel
 * van de website omdat iemand vergat het aan te melden -- en dat merk je
 * pas als de klant belt.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class SectionContent
{
    /**
     * Per sleutel een functie die het aantal items teruggeeft.
     *
     * @var array<string, callable(): int>
     */
    private array $tellers = [];

    /**
     * @param  callable(): int  $teller
     */
    public function telt(PageSectionKey $sectie, callable $teller): void
    {
        $this->tellers[$sectie->value] = $teller;
    }

    /**
     * Hoeveel items er in dit onderdeel staan, of null als het onderdeel
     * niets telbaars heeft.
     *
     * Het verschil tussen 0 en null is niet hetzelfde: 0 betekent "leeg en
     * dat is zichtbaar op het scherm", null betekent "hier valt niets te
     * tellen, want de tekst staat in de code".
     */
    public function aantal(PageSectionKey $sectie): ?int
    {
        $teller = $this->tellers[$sectie->value] ?? null;

        return $teller === null ? null : $teller();
    }

    public function gevuld(PageSectionKey $sectie): bool
    {
        $aantal = $this->aantal($sectie);

        return $aantal === null || $aantal > 0;
    }

    /**
     * Alles in één keer, zodat een scherm niet per onderdeel een vraag
     * hoeft te stellen.
     *
     * @param  array<int, PageSectionKey>  $secties
     * @return array<string, array{aantal: int|null, gevuld: bool}>
     */
    public function voor(array $secties): array
    {
        $uitkomst = [];

        foreach ($secties as $sectie) {
            $aantal = $this->aantal($sectie);

            $uitkomst[$sectie->value] = [
                'aantal' => $aantal,
                'gevuld' => $aantal === null || $aantal > 0,
            ];
        }

        return $uitkomst;
    }

    /**
     * Alle tellers weghalen.
     *
     * Alleen voor tests: die zetten een eigen teller neer en horen elkaar
     * niet te beïnvloeden.
     */
    public function vergeetAlles(): void
    {
        $this->tellers = [];
    }
}
