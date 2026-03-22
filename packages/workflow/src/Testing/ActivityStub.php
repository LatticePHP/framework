<?php

declare(strict_types=1);

namespace Lattice\Workflow\Testing;

/**
 * A test double for activities. Returns pre-configured results.
 */
final class ActivityStub
{
    /** @var array<string, mixed> method name => result */
    private array $results = [];

    /** @var array<string, \Throwable> method name => exception to throw */
    private array $exceptions = [];

    /** @var list<array{method: string, args: array}> */
    private array $calls = [];

    /**
     * Configure a method to return a specific result.
     */
    public function willReturn(string $method, mixed $result): self
    {
        $this->results[$method] = $result;
        return $this;
    }

    /**
     * Configure a method to throw an exception.
     */
    public function willThrow(string $method, \Throwable $exception): self
    {
        $this->exceptions[$method] = $exception;
        return $this;
    }

    /**
     * Magic method to handle any activity method call.
     */
    public function __call(string $name, array $arguments): mixed
    {
        $this->calls[] = ['method' => $name, 'args' => $arguments];

        if (isset($this->exceptions[$name])) {
            throw $this->exceptions[$name];
        }

        if (isset($this->results[$name])) {
            return $this->results[$name];
        }

        return null;
    }

    /**
     * Get all recorded calls.
     *
     * @return list<array{method: string, args: array}>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * Assert a method was called.
     */
    public function assertCalled(string $method): void
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                return;
            }
        }

        throw new \RuntimeException("Expected method '{$method}' to have been called");
    }

    /**
     * Assert a method was called with specific arguments.
     */
    public function assertCalledWith(string $method, array $expectedArgs): void
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === $method && $call['args'] === $expectedArgs) {
                return;
            }
        }

        throw new \RuntimeException(
            "Expected method '{$method}' to have been called with the given arguments"
        );
    }
}
