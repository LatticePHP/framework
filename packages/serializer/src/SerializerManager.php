<?php

declare(strict_types=1);

namespace Lattice\Serializer;

use Lattice\Contracts\Serializer\SerializerInterface;

final class SerializerManager
{
    /** @var array<string, SerializerInterface> */
    private array $serializers = [];

    private string $default = 'json';

    public function register(string $name, SerializerInterface $serializer): void
    {
        $this->serializers[$name] = $serializer;
    }

    public function get(?string $name = null): SerializerInterface
    {
        $name ??= $this->default;

        if (!isset($this->serializers[$name])) {
            throw new \InvalidArgumentException(
                sprintf('Serializer "%s" is not registered', $name),
            );
        }

        return $this->serializers[$name];
    }

    public function setDefault(string $name): void
    {
        $this->default = $name;
    }
}
