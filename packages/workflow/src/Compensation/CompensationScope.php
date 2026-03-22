<?php

declare(strict_types=1);

namespace Lattice\Workflow\Compensation;

use RuntimeException;

final class CompensationScope
{
    /** @var list<callable> */
    private array $compensations = [];

    public function addCompensation(callable $compensation): void
    {
        $this->compensations[] = $compensation;
    }

    /**
     * Runs all compensations in reverse order.
     * Collects all errors and throws an aggregate exception if any fail.
     *
     * @throws CompensationException
     */
    public function compensate(): void
    {
        $errors = [];

        foreach (array_reverse($this->compensations) as $i => $compensation) {
            try {
                $compensation();
            } catch (\Throwable $e) {
                $errors[] = $e;
            }
        }

        if (!empty($errors)) {
            throw new CompensationException(
                'One or more compensations failed',
                $errors,
            );
        }
    }

    /**
     * Runs the action, registering the compensation.
     * If the action fails, the compensation is NOT added (nothing to compensate).
     */
    public function run(callable $action, callable $compensation): mixed
    {
        $result = $action();
        $this->addCompensation($compensation);

        return $result;
    }
}
