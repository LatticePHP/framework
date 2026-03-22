<?php

declare(strict_types=1);

namespace Lattice\DevTools;

interface GeneratorInterface
{
    public function getName(): string;

    public function getDescription(): string;

    /**
     * @param array<string, mixed> $options
     * @return GeneratedFile[]
     */
    public function generate(array $options): array;
}
