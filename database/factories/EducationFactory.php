<?php

namespace Database\Factories;

use App\Models\Education;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Alleen voor tests.
 *
 * @extends Factory<Education>
 */
class EducationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $begin = fake()->dateTimeBetween('-20 years', '-6 years')
            ->modify('first day of this month');

        return [
            'published' => true,
            'title_nl' => fake()->words(3, true),
            'title_en' => null,
            'institution' => fake()->company(),
            'level_nl' => null,
            'level_en' => null,
            'started_on' => $begin,
            'ended_on' => (clone $begin)->modify('+4 years'),
            'machine_translated_at' => null,
        ];
    }

    /** Een opleiding die nog loopt; die hoort bovenaan te komen. */
    public function loopt(): static
    {
        return $this->state(fn () => ['ended_on' => null]);
    }

    /** Niet op de website. */
    public function offline(): static
    {
        return $this->state(fn () => ['published' => false]);
    }
}
