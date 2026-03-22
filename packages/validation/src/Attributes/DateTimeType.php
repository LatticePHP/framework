<?php

declare(strict_types=1);

namespace Lattice\Validation\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class DateTimeType
{
    public function __construct(public readonly string $format = 'Y-m-d\TH:i:sP')
    {
    }
}
