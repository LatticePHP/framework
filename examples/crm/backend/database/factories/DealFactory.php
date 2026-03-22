<?php

declare(strict_types=1);

namespace App\Database\Factories;

use App\Models\Deal;
use Lattice\Database\Factory;

final class DealFactory extends Factory
{
    protected $model = Deal::class;

    public function definition(): array
    {
        $stage = fake()->randomElement(Deal::STAGES);
        $probability = match ($stage) {
            'lead' => 10,
            'qualified' => 25,
            'proposal' => 50,
            'negotiation' => 75,
            'closed_won' => 100,
            'closed_lost' => 0,
        };

        return [
            'title' => fake()->catchPhrase() . ' Deal',
            'value' => fake()->randomFloat(2, 1000, 500000),
            'currency' => 'USD',
            'stage' => $stage,
            'probability' => $probability,
            'expected_close_date' => fake()->dateTimeBetween('+1 week', '+6 months')->format('Y-m-d'),
            'actual_close_date' => in_array($stage, ['closed_won', 'closed_lost'], true)
                ? fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d')
                : null,
            'lost_reason' => $stage === 'closed_lost'
                ? fake()->randomElement(['Budget', 'Competitor', 'No decision', 'Timing', 'Requirements mismatch'])
                : null,
        ];
    }

    /**
     * Set the deal as won.
     */
    public function won(): self
    {
        return $this->state(fn () => [
            'stage' => 'closed_won',
            'probability' => 100,
            'actual_close_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
        ]);
    }

    /**
     * Set the deal as lost.
     */
    public function lost(): self
    {
        return $this->state(fn () => [
            'stage' => 'closed_lost',
            'probability' => 0,
            'actual_close_date' => fake()->dateTimeBetween('-3 months', 'now')->format('Y-m-d'),
            'lost_reason' => fake()->randomElement(['Budget', 'Competitor', 'No decision']),
        ]);
    }
}
