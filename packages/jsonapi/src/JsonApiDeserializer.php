<?php

declare(strict_types=1);

namespace Lattice\JsonApi;

use ReflectionClass;
use ReflectionParameter;

final class JsonApiDeserializer
{
    /**
     * Deserialize a JSON:API request payload to an object of the target class.
     *
     * @template T of object
     * @param array<string, mixed> $data
     * @param class-string<T> $targetClass
     * @return T
     */
    public function deserialize(array $data, string $targetClass): object
    {
        if (!isset($data['data'])) {
            throw new \InvalidArgumentException('Missing "data" key in JSON:API payload.');
        }

        if (!isset($data['data']['attributes'])) {
            throw new \InvalidArgumentException('Missing "attributes" key in JSON:API data.');
        }

        if (!class_exists($targetClass)) {
            throw new \InvalidArgumentException("Target class '{$targetClass}' does not exist.");
        }

        $attributes = $data['data']['attributes'];
        $reflection = new ReflectionClass($targetClass);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $instance = $reflection->newInstance();
            foreach ($attributes as $key => $value) {
                if ($reflection->hasProperty($key)) {
                    $reflection->getProperty($key)->setValue($instance, $value);
                }
            }
            return $instance;
        }

        $args = [];
        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            if (array_key_exists($name, $attributes)) {
                $args[] = $attributes[$name];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                $args[] = null;
            }
        }

        return $reflection->newInstanceArgs($args);
    }
}
