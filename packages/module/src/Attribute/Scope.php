<?php

declare(strict_types=1);

namespace Lattice\Module\Attribute;

use Attribute;
use Lattice\Module\Scope\ScopeType;

#[Attribute(Attribute::TARGET_CLASS)]
final class Scope
{
    public function __construct(
        public readonly ScopeType $scope,
    ) {}
}
