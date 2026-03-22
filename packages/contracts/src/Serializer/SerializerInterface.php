<?php

declare(strict_types=1);

namespace Lattice\Contracts\Serializer;

interface SerializerInterface
{
    public function serialize(mixed $data): string;

    public function deserialize(string $data, string $type): mixed;
}
