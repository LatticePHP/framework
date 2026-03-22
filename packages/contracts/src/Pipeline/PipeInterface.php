<?php

declare(strict_types=1);

namespace Lattice\Contracts\Pipeline;

interface PipeInterface
{
    public function transform(mixed $value, array $metadata = []): mixed;
}
