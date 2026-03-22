<?php

declare(strict_types=1);

namespace Lattice\Loom;

interface HasTags
{
    /** @return string[] */
    public function tags(): array;
}
