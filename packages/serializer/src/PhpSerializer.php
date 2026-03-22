<?php

declare(strict_types=1);

namespace Lattice\Serializer;

use Lattice\Contracts\Serializer\SerializerInterface;

final class PhpSerializer implements SerializerInterface
{
    public function serialize(mixed $data): string
    {
        try {
            return \serialize($data);
        } catch (\Throwable $e) {
            throw SerializationException::serializeFailed($e->getMessage(), $e);
        }
    }

    public function deserialize(string $data, string $type): mixed
    {
        $allowedClasses = $type === 'array' ? false : [$type];

        $result = @\unserialize($data, ['allowed_classes' => $allowedClasses]);

        if ($result === false && $data !== \serialize(false)) {
            throw SerializationException::deserializeFailed(
                'Failed to unserialize data',
            );
        }

        return $result;
    }
}
