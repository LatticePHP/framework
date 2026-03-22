<?php

declare(strict_types=1);

namespace Lattice\Database\Seeder;

use Lattice\Database\ConnectionInterface;

final class SeederRunner
{
    public function __construct(
        private readonly ConnectionInterface $connection,
    ) {}

    /**
     * Run a single seeder class.
     */
    public function run(string $seederClass): void
    {
        /** @var Seeder $seeder */
        $seeder = new $seederClass($this->connection);
        $seeder->run();
    }

    /**
     * Run multiple seeder classes.
     *
     * @param list<string> $seederClasses
     */
    public function runAll(array $seederClasses): void
    {
        foreach ($seederClasses as $class) {
            $this->run($class);
        }
    }
}
