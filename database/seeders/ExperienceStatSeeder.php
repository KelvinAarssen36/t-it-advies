<?php

namespace Database\Seeders;

use App\Enums\ExperienceStatKey;
use App\Enums\ExperienceStatModus;
use App\Models\ExperienceStat;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Zet de drie standaardcijfers boven de tijdlijn klaar, op automatisch.
 *
 * Dit is productiedata en geen testdata: zonder deze rijen heeft het
 * beheerscherm niets om te tonen.
 *
 * **Alleen op een lege tabel, en dat is anders dan hoe deze seeder eerst
 * werkte.** Toen waren het drie vaste rijen die niet konden verdwijnen en
 * vulde hij aan wat ontbrak. Nu is het een lijst die de klant beheert --
 * hij mag cijfers hernoemen, verbergen en weghalen -- en dan is aanvullen
 * hetzelfde als opdringen: elke deploy zou zijn verwijderde cijfer
 * terugzetten, op zijn eigen voorpagina.
 *
 * Zie ook ExperienceHeadingSeeder; hetzelfde patroon en om dezelfde reden.
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
        if (ExperienceStat::query()->exists()) {
            return;
        }

        $plek = 0;

        foreach (ExperienceStatKey::cases() as $cijfer) {
            // Een eigen cijfer heeft geen standaardwaarde: dat verzint de
            // klant zelf, en tot die tijd hoort het er niet te staan.
            if (! $cijfer->berekenbaar()) {
                continue;
            }

            ExperienceStat::query()->create([
                'key' => $cijfer,
                // Leeg betekent: gebruik het standaardwoord van dit soort.
                // Dat is de stand waarin het woord vanzelf meegaat met de
                // taal van de bezoeker.
                'label_nl' => null,
                'label_en' => null,
                'value' => null,
                'modus' => ExperienceStatModus::Automatisch,
                'position' => $plek++,
            ]);
        }
    }
}
