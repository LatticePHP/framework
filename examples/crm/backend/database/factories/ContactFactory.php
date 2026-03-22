<?php

declare(strict_types=1);

namespace App\Database\Factories;

use App\Models\Contact;
use Lattice\Database\Factory;

final class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'title' => fake()->jobTitle(),
            'status' => fake()->randomElement(['lead', 'prospect', 'customer', 'churned', 'inactive']),
            'source' => fake()->randomElement(['web', 'referral', 'campaign', 'social', 'cold_call', 'trade_show', 'other']),
            'tags' => fake()->randomElements(['vip', 'enterprise', 'smb', 'startup', 'partner', 'hot-lead'], fake()->numberBetween(0, 3)),
        ];
    }

    /**
     * Define a contact with 'lead' status.
     */
    public function lead(): self
    {
        return $this->state(fn () => ['status' => 'lead']);
    }

    /**
     * Define a contact with 'customer' status.
     */
    public function customer(): self
    {
        return $this->state(fn () => ['status' => 'customer']);
    }
}
