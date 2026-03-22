<?php

declare(strict_types=1);

namespace Lattice\Microservices\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class MessageController
{
    public function __construct(
        public readonly ?string $transport = null,
    ) {}
}
