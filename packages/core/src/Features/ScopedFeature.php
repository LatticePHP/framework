<?php

declare(strict_types=1);

namespace Lattice\Core\Features;

final class ScopedFeature
{
    public function __construct(
        private readonly object $scope,
    ) {}

    public function active(string $name): bool
    {
        return Feature::active($name, $this->scope);
    }
}
