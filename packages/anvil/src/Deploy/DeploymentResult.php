<?php

declare(strict_types=1);

namespace Lattice\Anvil\Deploy;

final class DeploymentResult
{
    /**
     * @param list<string> $completedSteps
     * @param list<string> $errors
     */
    public function __construct(
        public readonly bool $success,
        public readonly array $completedSteps,
        public readonly float $duration,
        public readonly array $errors = [],
    ) {
    }
}
