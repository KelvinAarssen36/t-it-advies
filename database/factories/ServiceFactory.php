<?php

namespace Database\Factories;

use App\Enums\ServiceIcon;
use App\Models\Service;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Alleen voor tests.
 *
 * De echte diensten staan in ServiceSeeder als startpunt, en daarna
 * schrijft de klant ze om. Deze fabriek is er om in een test snel een
 * dienst neer te zetten zonder aan die tekst vast te zitten.
 *
 * @extends Factory<Service>
 */
class ServiceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'icon' => fake()->randomElement(ServiceIcon::cases()),

            // Standaard online, want daar gaat vrijwel elke test over:
            // wat de bezoeker te zien krijgt.
            'published' => true,
            'position' => fake()->numberBetween(1, 50),
            'title_nl' => fake()->words(2, true),
            'title_en' => null,
            'summary_nl' => fake()->sentence(),
            'summary_en' => null,
            'body_nl' => null,
            'body_en' => null,
            'machine_translated_at' => null,
        ];
    }

    /** Een dienst met een uitgebreide tekst, dus met een venster. */
    public function metVerhaal(): static
    {
        return $this->state(fn () => ['body_nl' => fake()->paragraphs(2, true)]);
    }

    /** Niet op de website. */
    public function offline(): static
    {
        return $this->state(fn () => ['published' => false]);
    }
}
