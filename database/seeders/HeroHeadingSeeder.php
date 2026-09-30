<?php

namespace Database\Seeders;

use App\Models\HeroHeading;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Zet de kop van de landingspagina klaar.
 *
 * Dit is de tekst die tot nu toe in HeroSection.vue stond. Hij hoort in
 * elke omgeving te bestaan, want zonder rij heeft het beheerscherm niets
 * te tonen en valt de website terug op `HeroHeading::huidige()`.
 *
 * **Het is een startpunt en geen definitieve tekst.** Anders dan bij de
 * tijdlijn, waar de seeder de echte loopbaan van de eigenaar bevat, staat
 * hier tekst die er vooral is zodat de voorpagina niet leeg is. De klant
 * schrijft hem om zodra hij weet wat er moet staan; dat is precies
 * waarvoor dit scherm bestaat.
 *
 * **De seeder is aanvullend, niet leidend.** Staat er al een rij, dan
 * blijft die staan -- ook al heeft de klant hem aangepast. Zou de seeder
 * hem terugzetten, dan gooit elke deploy zijn tekst weg, en dat ziet hij
 * op zijn eigen voorpagina.
 *
 * Zie ook ExperienceHeadingSeeder; hetzelfde patroon en om dezelfde reden.
 */
class HeroHeadingSeeder extends Seeder
{
    /*
     * Zaaien is geen handeling van de eigenaar, dus het hoort niet in zijn
     * activiteitenlogboek.
     */
    use WithoutModelEvents;

    public function run(): void
    {
        if (HeroHeading::query()->exists()) {
            return;
        }

        HeroHeading::query()->create([
            'eyebrow_nl' => 'IT-advies en realisatie',
            'eyebrow_en' => 'IT advice and delivery',
            'title_nl' => 'Techniek die doet wat je bedrijf nodig heeft.',
            'title_en' => 'Technology that does what your business needs.',
            'intro_nl' => 'Van advies tot bouw en beheer. Zonder ruis, zonder afhankelijkheid van één leverancier.',
            'intro_en' => 'From advice to building and management. No noise, and no dependence on a single supplier.',
        ]);
    }
}
