<?php

declare(strict_types=1);

namespace Lattice\Module\Provider;

final class ProviderOverrideRegistry
{
    /** @var array<string, mixed> */
    private array $overrides = [];

    /**
     * Override a provider binding. Used in testing to swap implementations.
     */
    public function override(string $abstract, mixed $concrete): void
    {
        $this->overrides[$abstract] = $concrete;
    }

    /**
     * Check if a provider has been overridden.
     */
    public function hasOverride(string $abstract): bool
    {
        return array_key_exists($abstract, $this->overrides);
    }

    /**
     * Get the override for a provider, or null if not overridden.
     */
    public function getOverride(string $abstract): mixed
    {
        return $this->overrides[$abstract] ?? null;
    }

    /**
     * Clear all overrides.
     */
    public function clearOverrides(): void
    {
        $this->overrides = [];
    }

    /**
     * Get all current overrides.
     *
     * @return array<string, mixed>
     */
    public function allOverrides(): array
    {
        return $this->overrides;
    }
}
