<?php

declare(strict_types=1);

namespace Lattice\Core\Container;

use Lattice\Contracts\Container\ContainerInterface;

final class Container implements ContainerInterface
{
    /** @var array<string, array{concrete: mixed, shared: bool}> */
    private array $bindings = [];

    /** @var array<string, mixed> */
    private array $instances = [];

    public function bind(string $abstract, mixed $concrete = null): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared' => false,
        ];
    }

    public function singleton(string $abstract, mixed $concrete = null): void
    {
        $this->bindings[$abstract] = [
            'concrete' => $concrete ?? $abstract,
            'shared' => true,
        ];
    }

    public function instance(string $abstract, mixed $instance): void
    {
        $this->instances[$abstract] = $instance;
    }

    public function make(string $abstract, array $parameters = []): mixed
    {
        // Check instances first
        if (array_key_exists($abstract, $this->instances)) {
            return $this->instances[$abstract];
        }

        // Check bindings
        if (isset($this->bindings[$abstract])) {
            $binding = $this->bindings[$abstract];
            $concrete = $binding['concrete'];

            $object = $this->resolve($concrete, $parameters);

            if ($binding['shared']) {
                $this->instances[$abstract] = $object;
            }

            return $object;
        }

        // Try auto-wiring
        return $this->autowire($abstract, $parameters);
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new NotFoundException("No binding found for '{$id}'.");
        }

        return $this->make($id);
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->instances) || isset($this->bindings[$id]);
    }

    public function reset(): void
    {
        $this->instances = [];
    }

    private function resolve(mixed $concrete, array $parameters): mixed
    {
        if ($concrete instanceof \Closure) {
            return $concrete($this, ...$parameters);
        }

        if (is_string($concrete) && class_exists($concrete)) {
            return $this->autowire($concrete, $parameters);
        }

        return $concrete;
    }

    private function autowire(string $class, array $parameters): mixed
    {
        if (!class_exists($class)) {
            throw new \RuntimeException("Cannot resolve '{$class}': class does not exist.");
        }

        $reflector = new \ReflectionClass($class);

        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("Cannot resolve '{$class}': class is not instantiable.");
        }

        $constructor = $reflector->getConstructor();

        if ($constructor === null) {
            return $reflector->newInstance();
        }

        $deps = [];

        foreach ($constructor->getParameters() as $param) {
            $name = $param->getName();

            // Check explicit parameters
            if (array_key_exists($name, $parameters)) {
                $deps[] = $parameters[$name];
                continue;
            }

            // Try to resolve by type
            $type = $param->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();

                try {
                    $deps[] = $this->make($typeName);
                    continue;
                } catch (\RuntimeException) {
                    // Fall through to default
                }
            }

            // Check for default value
            if ($param->isDefaultValueAvailable()) {
                $deps[] = $param->getDefaultValue();
                continue;
            }

            throw new \RuntimeException(
                "Cannot resolve parameter '\${$name}' for '{$class}'."
            );
        }

        return $reflector->newInstanceArgs($deps);
    }
}
