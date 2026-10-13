<?php

namespace Database\Seeders;

use App\Enums\PageSectionKey;
use App\Models\PageSection;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Zet de onderdelen van de landingspagina klaar.
 *
 * Eén rij per `PageSectionKey`. De rij bewaart alleen de **volgorde** en
 * of het onderdeel aan staat; wát een onderdeel is staat in die enum.
 *
 * **De seeder is aanvullend, niet leidend.** Een onderdeel dat er al
 * staat blijft staan zoals de klant het heeft gezet -- zijn volgorde en
 * zijn schuifjes blijven van hem. Wat hij wél doet is nieuwe onderdelen
 * erbij zetten en verdwenen onderdelen opruimen.
 *
 * Zie docs/architecture/pagina-indeling.md.
 */
class PageSectionSeeder extends Seeder
{
    /*
     * Zaaien is geen handeling van de eigenaar, dus het hoort niet in
     * zijn activiteitenlogboek.
     */
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (PageSectionKey::cases() as $sectie) {
            PageSection::query()->firstOrCreate(
                ['key' => $sectie],
                [
                    'position' => $this->plekVoor($sectie),
                    'visible' => true,
                ],
            );
        }

        $this->ruimOp();
        $this->herstelDubbelePlekken();
    }

    /**
     * Waar een nieuw onderdeel komt te staan.
     *
     * **Op een verse database de plek uit de enum**, want dan klopt de
     * hele rij in één keer: kop, diensten, werkwijze, en zo verder.
     *
     * **In een database die al gevuld is achteraan.** Dat is de
     * correctie op een fout die hier zat: `standaardPositie()` geldt
     * alleen voor de eerste keer zaaien. Zodra de klant zijn indeling
     * één keer heeft opgeslagen staan de onderdelen hernummerd als 1, 2,
     * 3 -- en dan botst de standaardplek van een nieuw onderdeel met een
     * plek die al bezet is. Twee onderdelen op dezelfde plek vallen
     * terug op hun `id`, en dan komt een nieuwe module op een
     * willekeurige plek in zijn eigen pagina terecht.
     *
     * Achteraan is bovendien hoe het overal elders in dit project gaat:
     * een nieuwe dienst, een nieuw certificaat en een nieuwe statistiek
     * komen ook achteraan, en waar het dan hoort bepaalt de klant met
     * slepen.
     *
     * De voettekst wordt niet meegeteld: die staat op duizend en hoort
     * onderaan te blijven.
     */
    private function plekVoor(PageSectionKey $sectie): int
    {
        if (! PageSection::query()->exists()) {
            return $sectie->standaardPositie();
        }

        if ($sectie->vast()) {
            return $sectie->standaardPositie();
        }

        return (int) PageSection::query()
            ->whereIn('key', $this->verplaatsbareSleutels())
            ->max('position') + 1;
    }

    /**
     * Twee onderdelen op dezelfde plek: opnieuw doornummeren.
     *
     * Dit repareert de databases waarin de fout hierboven al heeft
     * toegeslagen. Het **behoudt de volgorde die de klant had** -- er
     * wordt alleen opnieuw genummerd, in de volgorde waarin de
     * onderdelen nu al staan -- dus hij merkt er niets van behalve dat
     * het nieuwe onderdeel op zijn eigen plek staat en niet ergens
     * tussen.
     *
     * Hij doet niets zodra de nummering al klopt, dus opnieuw zaaien is
     * veilig.
     *
     * De vaste onderdelen blijven buiten dit sommetje: die staan op nul
     * en duizend, en het indelingsscherm zet ze boven- en onderaan omdát
     * ze vast zijn en niet vanwege hun getal.
     */
    private function herstelDubbelePlekken(): void
    {
        $rijen = PageSection::query()
            ->whereIn('key', $this->verplaatsbareSleutels())
            ->opVolgorde()
            ->get();

        $plekken = $rijen->pluck('position');

        if ($plekken->count() === $plekken->unique()->count()) {
            return;
        }

        foreach ($rijen as $index => $rij) {
            $rij->position = $index + 1;
            $rij->save();
        }
    }

    /**
     * De sleutels van de onderdelen die de klant kan verslepen.
     *
     * @return array<int, string>
     */
    private function verplaatsbareSleutels(): array
    {
        return array_map(
            fn (PageSectionKey $sectie) => $sectie->value,
            PageSectionKey::verplaatsbaar(),
        );
    }

    /**
     * Rijen opruimen waarvan het onderdeel niet meer bestaat.
     *
     * Halen we ooit een onderdeel uit de enum, dan blijft zijn rij
     * anders in de database staan -- en dan telt het indelingsscherm een
     * onderdeel mee dat nergens meer heen gaat.
     */
    private function ruimOp(): void
    {
        $bestaand = array_map(
            fn (PageSectionKey $sectie) => $sectie->value,
            PageSectionKey::cases(),
        );

        PageSection::query()->whereNotIn('key', $bestaand)->delete();
    }
}
