<?php

declare(strict_types=1);

namespace Lattice\Queue\Failed;

use Lattice\Queue\SerializedJob;

interface FailedJobStoreInterface
{
    public function store(SerializedJob $job, \Throwable $exception): void;

    /** @return FailedJob[] */
    public function all(): array;

    public function find(string $id): ?FailedJob;

    public function retry(string $id): void;

    public function delete(string $id): void;

    public function flush(): void;
}
