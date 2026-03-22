<?php

declare(strict_types=1);

namespace Lattice\Queue;

interface JobInterface
{
    public function handle(): void;

    public function getQueue(): string;

    public function getConnection(): string;

    public function getMaxAttempts(): int;

    public function getTimeout(): int;

    /** @return int[] */
    public function getBackoff(): array;
}
