<?php

namespace Database\Factories;

use App\Models\WorkStep;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Stappen voor de tests.
 *
 * De volgorde loopt op met een teller en niet met `fake()`: in deze module
 * bepaalt de positie ook het nummer op de kaart, dus een test die twee
 * stappen maakt moet erop kunnen rekenen dat de eerste ook echt eerst
 * staat.
 *
 * @extends Factory<WorkStep>
 */
class WorkStepFactory extends Factory
{
    protected $model = WorkStep::class;

    /** Loopt op binnen één testrun; zie het blok hierboven. */
    private static int $teller = 0;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        self::$teller++;

        return [
            'position' => self::$teller,
            'published' => true,

            'title_nl' => ucfirst(implode(' ', (array) fake()->unique()->words(2))),
            'title_en' => null,

            'summary_nl' => fake()->sentence(),
            'summary_en' => null,

            'duration_nl' => null,
            'duration_en' => null,

            'result_nl' => null,
            'result_en' => null,

            'body_nl' => null,
            'body_en' => null,

            'machine_translated_at' => null,
        ];
    }

    /** Een stap die niet op de website staat. */
    public function offline(): static
    {
        return $this->state(fn () => ['published' => false]);
    }

    /** Een stap met alles ingevuld, inclusief het verhaal voor /werkwijze. */
    public function uitgebreid(): static
    {
        return $this->state(fn () => [
            'duration_nl' => '1-2 weken',
            'result_nl' => 'Je krijgt een plan met een prijs.',
            'body_nl' => "Eerst kijken we samen naar wat er nu gebeurt.\n\nDaarna schrijf ik op wat er nodig is en wat het kost.",
        ]);
    }

    /** En hetzelfde, maar ook in het Engels. */
    public function tweetalig(): static
    {
        return $this->state(fn () => [
            'title_en' => 'Getting to know each other',
            'summary_en' => 'What is going on, what is already there, and where it gets stuck.',
            'duration_en' => '1-2 weeks',
            'result_en' => 'You get a plan with a price.',
            'body_en' => "First we look at what happens today.\n\nThen I write down what is needed and what it costs.",
        ]);
    }
}
