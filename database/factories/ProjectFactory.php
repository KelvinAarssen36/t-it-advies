<?php

namespace Database\Factories;

use App\Enums\ProjectType;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Alleen voor tests.
 *
 * Er wordt bewust géén project geseed: de eigenaar vult zijn etalage
 * zelf, en zolang hij dat niet heeft gedaan hoort het onderdeel niet op
 * de website te staan.
 *
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // `words(..., true)` geeft een string; de typedefinitie van de
        // fabriek kent dat onderscheid niet, vandaar de samenvoeging.
        $titel = implode(' ', (array) fake()->unique()->words(3));

        return [
            // Standaard online, want daar gaat vrijwel elke test over:
            // wat de bezoeker te zien krijgt.
            'published' => true,
            'featured' => false,
            'position' => fake()->numberBetween(1, 50),

            /*
             * Rechtstreeks een unieke slug en niet via `vrijeSlug()`: die
             * doet een query per aanroep, en een fabriek die er twintig
             * maakt doet er dan twintig voor niets. De uniciteit komt
             * hier van `unique()` op de titel plus een achtervoegsel.
             */
            'slug' => Str::slug($titel).'-'.fake()->unique()->numberBetween(1, 99999),

            'type' => ProjectType::Project,
            'type_label_nl' => null,
            'type_label_en' => null,

            'title_nl' => $titel,
            'title_en' => null,
            'organisation' => fake()->company(),
            'role_nl' => fake()->jobTitle(),
            'role_en' => null,

            // Op de eerste van de maand, want dat is de afspraak bij een
            // datum die alleen als maand wordt getoond.
            'started_on' => fake()->dateTimeBetween('-8 years', '-1 year')
                ->modify('first day of this month'),
            'ended_on' => fake()->dateTimeBetween('-11 months', '-1 month')
                ->modify('first day of this month'),

            'summary_nl' => fake()->sentence(12),
            'summary_en' => null,
            'body_nl' => null,
            'body_en' => null,
            'result_nl' => null,
            'result_en' => null,

            'image_path' => null,
            'machine_translated_at' => null,
        ];
    }

    /** Niet op de website. */
    public function offline(): static
    {
        return $this->state(fn () => ['published' => false]);
    }

    /** Het project dat vooraan staat. */
    public function uitgelicht(): static
    {
        return $this->state(fn () => ['featured' => true]);
    }

    /** Een project dat nu nog loopt. */
    public function loopt(): static
    {
        return $this->state(fn () => ['ended_on' => null]);
    }

    /** Met een eigen soort in plaats van een uit de lijst. */
    public function eigenType(): static
    {
        return $this->state(fn () => [
            'type' => ProjectType::Anders,
            'type_label_nl' => 'Haalbaarheidsonderzoek',
            'type_label_en' => 'Feasibility study',
        ]);
    }

    /** Met alle Engelse velden gevuld. */
    public function vertaald(): static
    {
        return $this->state(fn () => [
            'title_en' => fake()->words(3, true),
            'role_en' => fake()->jobTitle(),
            'summary_en' => fake()->sentence(12),
        ]);
    }

    /** Met de lange teksten erbij, dus met iets te lezen op de detailpagina. */
    public function uitgebreid(): static
    {
        return $this->state(fn () => [
            'body_nl' => fake()->paragraph(),
            'result_nl' => fake()->paragraph(),
        ]);
    }
}
