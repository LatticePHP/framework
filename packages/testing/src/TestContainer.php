<?php

declare(strict_types=1);

namespace Lattice\Testing;

use Psr\Container\ContainerInterface;

/**
 * Simple test container that resolves overrides for testing.
 */
final class TestContainer implements ContainerInterface
{
    /** @var array<string, mixed> */
    private array $bindings = [];

    /**
     * @param array<string, mixed> $overrides
     */
    public function __construct(array $overrides = [])
    {
        $this->bindings = $overrides;
    }

    public function get(string $id): mixed
    {
        if (!$this->has($id)) {
            throw new class ("No entry found for: {$id}") extends \RuntimeException implements \Psr\Container\NotFoundExceptionInterface {};
        }

        return $this->bindings[$id];
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->bindings);
    }

    public function set(string $id, mixed $value): void
    {
        $this->bindings[$id] = $value;
    }

    /**
     * Alias for set() — matches the ContainerInterface::instance() signature.
     */
    public function instance(string $abstract, mixed $instance): void
    {
        $this->bindings[$abstract] = $instance;
    }
}
