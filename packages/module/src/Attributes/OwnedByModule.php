<?php

declare(strict_types=1);

namespace Lattice\Module\Attributes;

use Attribute;

/**
 * Marks a model or class as owned by a specific module.
 *
 * This is metadata only — used by the compiler for cross-module
 * access warnings and dependency analysis.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class OwnedByModule
{
    public function __construct(
        public readonly string $module,
    ) {}
}
