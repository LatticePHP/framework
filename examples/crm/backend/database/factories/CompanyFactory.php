<?php

declare(strict_types=1);

namespace App\Database\Factories;

use App\Models\Company;
use Lattice\Database\Factory;

final class CompanyFactory extends Factory
{
    protected $model = Company::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'domain' => fake()->domainName(),
            'industry' => fake()->randomElement(['technology', 'finance', 'healthcare', 'manufacturing', 'retail', 'education', 'consulting', 'other']),
            'size' => fake()->randomElement(['1-10', '11-50', '51-200', '201-500', '501-1000', '1001+']),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->companyEmail(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'country' => fake()->country(),
            'website' => fake()->url(),
        ];
    }
}
