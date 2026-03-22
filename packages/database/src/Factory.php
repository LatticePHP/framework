<?php

declare(strict_types=1);

namespace Lattice\Database;

use Illuminate\Database\Eloquent\Factories\Factory as EloquentFactory;

/**
 * Base factory class for LatticePHP model factories.
 *
 * Subclasses must define the $model property and the definition() method.
 *
 * Example:
 *
 *     final class UserFactory extends Factory
 *     {
 *         protected $model = User::class;
 *
 *         public function definition(): array
 *         {
 *             return [
 *                 'name' => fake()->name(),
 *                 'email' => fake()->unique()->safeEmail(),
 *             ];
 *         }
 *     }
 */
abstract class Factory extends EloquentFactory
{
}
