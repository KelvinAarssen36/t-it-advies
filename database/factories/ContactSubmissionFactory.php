<?php

namespace Database\Factories;

use App\Models\ContactSubmission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactSubmission>
 */
class ContactSubmissionFactory extends Factory
{
    protected $model = ContactSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'locale' => 'nl',
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'company' => null,
            'phone' => null,
            'subject_id' => null,
            'subject_text' => fake()->sentence(3),
            'subject_custom' => true,
            'message' => fake()->paragraph(),

            // Wat er standaard aan staat; zie ContactVeld::standaardStatus().
            'shown' => ['name', 'email', 'subject', 'message'],

            'read_at' => null,
            'answered_at' => null,
        ];
    }

    public function gelezen(): static
    {
        return $this->state(fn () => ['read_at' => now()]);
    }

    public function beantwoord(): static
    {
        return $this->state(fn () => [
            'read_at' => now(),
            'answered_at' => now(),
        ]);
    }

    /** Een aanvraag van een Engelstalige bezoeker. */
    public function engels(): static
    {
        return $this->state(fn () => ['locale' => 'en']);
    }
}
