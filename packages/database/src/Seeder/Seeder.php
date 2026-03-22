<?php

declare(strict_types=1);

namespace Lattice\Database\Seeder;

use Lattice\Database\ConnectionInterface;

abstract class Seeder
{
    public function __construct(
        protected readonly ConnectionInterface $connection,
    ) {}

    /**
     * Run the database seeder.
     */
    abstract public function run(): void;

    /**
     * Call other seeder classes.
     */
    public function call(string ...$seederClasses): void
    {
        foreach ($seederClasses as $class) {
            /** @var Seeder $seeder */
            $seeder = new $class($this->connection);
            $seeder->run();
        }
    }
}
