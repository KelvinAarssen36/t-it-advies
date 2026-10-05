<?php

namespace Database\Factories;

use App\Models\ContactSubject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ContactSubject>
 */
class ContactSubjectFactory extends Factory
{
    protected $model = ContactSubject::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'label_nl' => fake()->randomElement([
                'Vrijblijvend gesprek',
                'Vraag over een dienst',
                'Offerte aanvragen',
                'Iets anders',
            ]).' '.fake()->unique()->numberBetween(1, 9999),
            'label_en' => null,
            'published' => true,
            'position' => 0,
            'featured' => false,
        ];
    }

    /** Een onderwerp dat niet op de website staat. */
    public function offline(): static
    {
        return $this->state(fn () => ['published' => false]);
    }

    /** Een onderwerp met een Engelse naam erbij. */
    public function tweetalig(): static
    {
        return $this->state(fn (array $kenmerken) => [
            'label_en' => 'Subject '.fake()->unique()->numberBetween(1, 9999),
        ]);
    }

    /** Een onderwerp dat de eigenaar heeft uitgelicht. */
    public function uitgelicht(): static
    {
        return $this->state(fn () => ['featured' => true]);
    }
}
