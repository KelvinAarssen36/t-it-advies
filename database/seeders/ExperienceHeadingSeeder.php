<?php

namespace Database\Seeders;

use App\Models\ExperienceHeading;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Zet de kop boven de tijdlijn klaar.
 *
 * Dit is productiedata en geen testdata: het is de tekst die tot nu toe in
 * het Vue-component stond, en die hoort op elke omgeving te bestaan.
 *
 * **De seeder is aanvullend, niet leidend.** Staat er al een rij, dan
 * blijft die staan -- ook al heeft de klant hem aangepast. Zou de seeder
 * hem terugzetten, dan gooit elke deploy zijn tekst weg, en dat ziet hij
 * op zijn eigen voorpagina.
 *
 * Zie ook ExperienceStatSeeder; hetzelfde patroon en om dezelfde redenen.
 */
class ExperienceHeadingSeeder extends Seeder
{
    /*
     * Zaaien is geen handeling van de eigenaar, dus het hoort niet in zijn
     * activiteitenlogboek.
     */
    use WithoutModelEvents;

    public function run(): void
    {
        if (ExperienceHeading::query()->exists()) {
            return;
        }

        ExperienceHeading::query()->create([
            'title_nl' => 'Waar dit vandaan komt',
            'title_en' => 'Where this comes from',
            'intro_nl' => 'De weg ernaartoe, van nu naar toen.',
            'intro_en' => 'The road here, from now back to then.',
        ]);
    }
}
