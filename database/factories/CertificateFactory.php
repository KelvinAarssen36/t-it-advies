<?php

namespace Database\Factories;

use App\Models\Certificate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Alleen voor tests.
 *
 * Er wordt bewust géén certificaat geseed: de eigenaar vult ze zelf, en
 * zolang hij dat niet heeft gedaan hoort het onderdeel niet op de
 * website te staan. Deze fabriek is er om in een test snel een
 * certificaat neer te zetten.
 *
 * @extends Factory<Certificate>
 */
class CertificateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // Standaard online, want daar gaat vrijwel elke test over:
            // wat de bezoeker te zien krijgt.
            'published' => true,
            'position' => fake()->numberBetween(1, 50),
            'logo_path' => null,
            'title_nl' => fake()->words(3, true),
            'title_en' => null,
            'issuer' => fake()->company(),

            // Op de eerste van de maand, want dat is de afspraak bij een
            // datum die alleen als maand wordt getoond.
            'issued_on' => fake()->dateTimeBetween('-8 years', '-1 month')
                ->modify('first day of this month'),

            // Standaard eeuwig geldig. Verlopen is een uitzondering en
            // heeft zijn eigen toestand hieronder.
            'expires_on' => null,

            'credential_id' => null,
            'body_nl' => null,
            'body_en' => null,
            'machine_translated_at' => null,
        ];
    }

    /** Een certificaat waarvan de geldigheid voorbij is. */
    public function verlopen(): static
    {
        return $this->state(fn () => [
            'issued_on' => now()->subYears(4)->startOfMonth(),
            'expires_on' => now()->subYear()->startOfMonth(),
        ]);
    }

    /** Eentje dat nog een tijd meegaat. */
    public function nogGeldig(): static
    {
        return $this->state(fn () => [
            'expires_on' => now()->addYear()->startOfMonth(),
        ]);
    }

    /** Met een toelichting, dus met een venster achter de tegel. */
    public function metToelichting(): static
    {
        return $this->state(fn () => ['body_nl' => fake()->paragraph()]);
    }

    /** Niet op de website. */
    public function offline(): static
    {
        return $this->state(fn () => ['published' => false]);
    }
}
