<?php

declare(strict_types=1);

namespace Lattice\Serializer;

final class SerializationException extends \RuntimeException
{
    public static function serializeFailed(string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Serialization failed: %s', $reason),
            0,
            $previous,
        );
    }

    public static function deserializeFailed(string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Deserialization failed: %s', $reason),
            0,
            $previous,
        );
    }
}
