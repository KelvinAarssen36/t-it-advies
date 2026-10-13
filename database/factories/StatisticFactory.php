<?php

namespace Database\Factories;

use App\Enums\StatisticDisplay;
use App\Models\Statistic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Alleen voor tests.
 *
 * Er worden geen statistieken geseed: de eigenaar vult ze zelf, en
 * zolang hij dat niet heeft gedaan hoort het onderdeel niet op de
 * website te staan.
 *
 * @extends Factory<Statistic>
 */
class StatisticFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Standaard een balk: dat is de weergave waar de meeste
            // statistieken op uitkomen, en de enige met een grens die
            // in bijna elke test meedoet.
            'display' => StatisticDisplay::Balk,

            'published' => true,
            'position' => fake()->numberBetween(1, 50),
            'label_nl' => fake()->words(2, true),
            'label_en' => null,
            'value' => fake()->numberBetween(40, 100),
            'prefix' => null,
            'suffix' => null,
            'note_nl' => null,
            'note_en' => null,
            'group_nl' => null,
            'group_en' => null,
            'machine_translated_at' => null,
        ];
    }

    public function ring(): static
    {
        return $this->state(fn () => ['display' => StatisticDisplay::Ring]);
    }

    /** Een teller heeft geen grens van honderd; vandaar het grotere getal. */
    public function teller(): static
    {
        return $this->state(fn () => [
            'display' => StatisticDisplay::Teller,
            'value' => fake()->numberBetween(100, 9999),
            'suffix' => '+',
        ]);
    }

    public function inGroep(string $groep): static
    {
        return $this->state(fn () => ['group_nl' => $groep]);
    }

    /** Niet op de website. */
    public function offline(): static
    {
        return $this->state(fn () => ['published' => false]);
    }
}
