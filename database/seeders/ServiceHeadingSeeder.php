<?php

namespace Database\Seeders;

use App\Models\ServiceHeading;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Zet de kop boven de diensten klaar.
 *
 * Dezelfde tekst die tot nu toe in DienstenSection.vue stond. Net als bij
 * de kop van de landingspagina is dit een startpunt: er staat wat, en de
 * klant schrijft het om.
 *
 * **De seeder is aanvullend, niet leidend.** Staat er al een rij, dan
 * blijft die staan -- ook al heeft de klant hem aangepast.
 *
 * Zie ook HeroHeadingSeeder; hetzelfde patroon en om dezelfde reden.
 */
class ServiceHeadingSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (ServiceHeading::query()->exists()) {
            return;
        }

        ServiceHeading::query()->create([
            'eyebrow_nl' => 'Diensten',
            'eyebrow_en' => 'Services',
            'title_nl' => 'Wat we doen',
            'title_en' => 'What we do',
            'intro_nl' => 'Drie dingen, en die goed. De rest besteden we liever uit dan half te doen.',
            'intro_en' => 'Three things, and done properly. We would rather outsource the rest than do it by halves.',
        ]);
    }
}
