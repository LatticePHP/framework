<?php

declare(strict_types=1);

namespace Lattice\Pipeline\Pipe;

use Lattice\Contracts\Pipeline\PipeInterface;

final class PipeChain
{
    /**
     * Run pipes in sequence to transform input.
     *
     * @param array<PipeInterface> $pipes
     */
    public function execute(array $pipes, mixed $input, array $metadata): mixed
    {
        $value = $input;

        foreach ($pipes as $pipe) {
            $value = $pipe->transform($value, $metadata);
        }

        return $value;
    }
}
