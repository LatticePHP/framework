<?php

declare(strict_types=1);

namespace Lattice\Ai\Exceptions;

use Lattice\Ai\Providers\ProviderCapability;

final class UnsupportedCapabilityException extends AiException
{
    public static function forCapability(string $provider, ProviderCapability $capability): self
    {
        return new self(
            "Provider [{$provider}] does not support [{$capability->value}].",
        );
    }
}
