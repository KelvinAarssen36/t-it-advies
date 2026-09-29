<?php

namespace Database\Seeders;

use App\Enums\ExperienceStatKey;
use App\Models\ExperienceStat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Zet de drie cijfers boven de tijdlijn klaar, allemaal op automatisch.
 *
 * Dit is productiedata en geen testdata: zonder deze rijen heeft het
 * beheerscherm niets om te tonen.
 *
 * **De seeder is aanvullend, niet leidend.** Een cijfer dat de klant zelf
 * heeft ingevuld blijft staan. Zou de seeder het terugzetten op
 * automatisch, dan gooit elke deploy zijn werk weg -- en dat ziet hij op
 * zijn eigen voorpagina.
 *
 * Zie ook PageSectionSeeder; dat is hetzelfde patroon en om dezelfde
 * redenen.
 */
class ExperienceStatSeeder extends Seeder
{
    /*
     * Zaaien is geen handeling van de eigenaar, dus het hoort niet in zijn
     * activiteitenlogboek. De trait staat hier en niet alleen op
     * DatabaseSeeder, zodat het ook klopt als iemand deze seeder los
     * aanroept.
     */
    use WithoutModelEvents;

    public function run(): void
    {
        foreach (ExperienceStatKey::cases() as $cijfer) {
            ExperienceStat::query()->firstOrCreate(
                ['key' => $cijfer],
                // Leeg betekent: uitrekenen op basis van de tijdlijn. Dat
                // is de stand waarin een cijfer vanzelf blijft kloppen.
                ['value' => null],
            );
        }

        $this->ruimOp();
    }

    /** Weg met rijen waarvan het cijfer niet meer bestaat. */
    private function ruimOp(): void
    {
        $bestaand = array_map(
            fn (ExperienceStatKey $cijfer) => $cijfer->value,
            ExperienceStatKey::cases(),
        );

        ExperienceStat::query()->whereNotIn('key', $bestaand)->delete();
    }
}
