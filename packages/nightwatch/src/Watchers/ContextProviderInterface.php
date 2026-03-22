<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Watchers;

interface ContextProviderInterface
{
    /**
     * Provide custom context for the exception entry.
     *
     * @return array<string, mixed>
     */
    public function context(): array;
}
