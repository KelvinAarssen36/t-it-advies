<?php

namespace Database\Factories;

use App\Enums\CoreFactIcon;
use App\Models\CoreFact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Alleen voor tests.
 *
 * Er worden geen kerngegevens geseed in `DatabaseSeeder`: wij verzinnen de
 * *soorten*, de eigenaar vult de *feiten*. Zou "KvK 12345678" meekomen,
 * dan staat er een onwaarheid op zijn live site tot hij hem toevallig
 * opmerkt. Zolang hij niets heeft ingevuld hoort het onderdeel niet op
 * zijn website te staan; dat regelt de teller in AppServiceProvider.
 *
 * Verzonnen data om de schermen mee te bekijken staat wél klaar, in
 * `VoorbeeldDataSeeder` -- en die weigert buiten `local` en `testing`.
 *
 * @extends Factory<CoreFact>
 */
class CoreFactFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'icon' => fake()->randomElement(CoreFactIcon::cases())->value,
            'label_nl' => fake()->words(2, true),
            'label_en' => null,
            'value_nl' => fake()->words(4, true),
            'value_en' => null,
            'note_nl' => null,
            'note_en' => null,
            'published' => true,
            'position' => fake()->numberBetween(1, 50),
            'machine_translated_at' => null,
        ];
    }

    /** Niet op de website. */
    public function offline(): static
    {
        return $this->state(fn () => ['published' => false]);
    }

    /** Met een Engels label én een Engelse waarde. */
    public function tweetalig(): static
    {
        return $this->state(fn () => [
            'label_en' => fake()->words(2, true),
            'value_en' => fake()->words(4, true),
        ]);
    }

    /**
     * Een waarde die langer is dan het rolbord aankan.
     *
     * Boven de 24 tekens komt de waarde als geheel omhoog in plaats van
     * letter voor letter; zie `rolbord()` in resources/js/lib/motion.ts.
     */
    public function metLangeWaarde(): static
    {
        return $this->state(fn () => [
            'value_nl' => 'Op locatie, op afstand of een combinatie daarvan',
        ]);
    }
}
