<?php

declare(strict_types=1);

namespace Lattice\Ai\Exceptions;

final class ProviderNotFoundException extends AiException
{
    public static function forName(string $name): self
    {
        return new self("AI provider [{$name}] is not registered.");
    }
}
