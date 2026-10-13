<?php

namespace Database\Factories;

use App\Models\FaqItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Alleen voor tests.
 *
 * Er worden geen vragen geseed: de eigenaar schrijft ze zelf, en zolang
 * hij dat niet heeft gedaan hoort het onderdeel niet op zijn website te
 * staan. Dat regelt de teller in AppServiceProvider.
 *
 * @extends Factory<FaqItem>
 */
class FaqItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_nl' => rtrim(fake()->sentence(6), '.').'?',
            'question_en' => null,
            'answer_nl' => fake()->paragraph(),
            'answer_en' => null,
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

    /** Met een Engelse vraag én een Engels antwoord. */
    public function tweetalig(): static
    {
        return $this->state(fn () => [
            'question_en' => rtrim(fake()->sentence(6), '.').'?',
            'answer_en' => fake()->paragraph(),
        ]);
    }

    /**
     * Een antwoord met een witregel erin.
     *
     * Dat is de vorm waar het om gaat: `brand-blad-tekst` maakt er op de
     * website twee alinea's van.
     */
    public function metAlineas(): static
    {
        return $this->state(fn () => [
            'answer_nl' => fake()->paragraph()."\n\n".fake()->paragraph(),
        ]);
    }
}
