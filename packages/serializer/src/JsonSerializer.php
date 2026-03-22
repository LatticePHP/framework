<?php

declare(strict_types=1);

namespace Lattice\Serializer;

use Lattice\Contracts\Serializer\SerializerInterface;

final class JsonSerializer implements SerializerInterface
{
    public function serialize(mixed $data): string
    {
        try {
            return json_encode($data, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        } catch (\JsonException $e) {
            throw SerializationException::serializeFailed($e->getMessage(), $e);
        }
    }

    public function deserialize(string $data, string $type): mixed
    {
        try {
            $decoded = json_decode($data, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw SerializationException::deserializeFailed($e->getMessage(), $e);
        }

        if ($type === 'array') {
            return $decoded;
        }

        if ($type === 'string') {
            return (string) $decoded;
        }

        if ($type === 'int') {
            return (int) $decoded;
        }

        if ($type === 'float') {
            return (float) $decoded;
        }

        if ($type === 'bool') {
            return (bool) $decoded;
        }

        // For class types, hydrate from array
        return $this->hydrate($decoded, $type);
    }

    private function hydrate(array $data, string $class): object
    {
        if (!class_exists($class)) {
            throw SerializationException::deserializeFailed(
                sprintf('Class "%s" does not exist', $class),
            );
        }

        $ref = new \ReflectionClass($class);
        $constructor = $ref->getConstructor();

        if ($constructor === null) {
            $instance = $ref->newInstance();
            foreach ($data as $key => $value) {
                if ($ref->hasProperty($key)) {
                    $prop = $ref->getProperty($key);
                    $prop->setAccessible(true);
                    $prop->setValue($instance, $value);
                }
            }
            return $instance;
        }

        // Constructor-based hydration
        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();
            if (array_key_exists($name, $data)) {
                $args[] = $data[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return $ref->newInstanceArgs($args);
    }
}
