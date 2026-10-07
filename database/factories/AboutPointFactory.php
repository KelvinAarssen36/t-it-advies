<?php

namespace Database\Factories;

use App\Models\AboutPoint;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Alleen voor tests.
 *
 * Er worden geen punten geseed: de eigenaar schrijft ze zelf, en zolang hij
 * dat niet heeft gedaan hoort er op de pagina geen leeg lijstje te staan.
 *
 * @extends Factory<AboutPoint>
 */
class AboutPointFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'position' => fake()->numberBetween(1, 20),
            'text_nl' => fake()->words(3, true),
            'text_en' => null,
        ];
    }

    /** Met een Engelse tekst erbij. */
    public function tweetalig(): static
    {
        return $this->state(fn () => ['text_en' => fake()->words(3, true)]);
    }
}
