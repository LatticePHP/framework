<?php

declare(strict_types=1);

namespace Lattice\Core\Features;

interface FeatureScopeable
{
    public function featureScopeIdentifier(): string;
}
