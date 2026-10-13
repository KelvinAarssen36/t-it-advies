<?php

namespace Database\Factories;

use App\Enums\EmploymentType;
use App\Enums\ExperienceIcon;
use App\Enums\WorkplaceType;
use App\Models\Experience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Alleen voor tests.
 *
 * De echte ervaringen voert de klant zelf in; er is geen seeder voor. Dat
 * is het verschil met de secties van de pagina: welke onderdelen er bestaan
 * bepalen wij, wat erin staat bepaalt hij.
 *
 * @extends Factory<Experience>
 */
class ExperienceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-10 years', '-2 years');

        return [
            'icon' => fake()->randomElement(ExperienceIcon::cases()),
            // Standaard online, want dat is waar vrijwel elke test over
            // gaat: wat de bezoeker te zien krijgt.
            'published' => true,
            'logo_path' => null,
            'role_nl' => fake()->jobTitle(),
            'role_en' => null,
            'organisation' => fake()->company(),
            'organisation_url' => null,
            'employment' => fake()->randomElement(EmploymentType::cases()),
            'workplace' => fake()->randomElement(WorkplaceType::cases()),
            'location_nl' => fake()->city(),
            'location_en' => null,
            'started_on' => $start,
            'ended_on' => fake()->dateTimeBetween($start, '-1 month'),
            'description_nl' => fake()->paragraph(),
            'description_en' => null,
            'machine_translated_at' => null,
        ];
    }

    /** De ervaring die nu nog loopt: geen einddatum, dus "tot heden". */
    public function loopt(): static
    {
        return $this->state(fn () => ['ended_on' => null]);
    }

    /** Alleen het hoogstnodige: geen plaats, geen tekst, geen etiketten. */
    public function kaal(): static
    {
        return $this->state(fn () => [
            'employment' => null,
            'workplace' => null,
            'location_nl' => null,
            'description_nl' => null,
        ]);
    }

    /** Wel ingevoerd, maar niet op de website gezet. */
    public function offline(): static
    {
        return $this->state(fn () => ['published' => false]);
    }

    /** Met een Engelse vertaling erbij. */
    public function vertaald(): static
    {
        return $this->state(fn (array $waarden) => [
            'role_en' => 'Translated role',
            'location_en' => 'Translated place',
            'description_en' => 'Translated description.',
        ]);
    }
}
