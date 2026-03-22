<?php

declare(strict_types=1);

namespace Lattice\DevTools;

final class GeneratedFile
{
    /**
     * @param 'created'|'modified' $type
     */
    public function __construct(
        public readonly string $path,
        public readonly string $content,
        public readonly string $type = 'created',
    ) {}
}
