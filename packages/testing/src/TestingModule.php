<?php

declare(strict_types=1);

namespace Lattice\Testing;

/**
 * Creates a test application with module overrides for integration testing.
 *
 * Usage:
 *   $container = TestingModule::create(AppModule::class)
 *       ->overrideProvider(UserService::class, MockUserService::class)
 *       ->overrideProvider(PaymentGateway::class, FakePaymentGateway::class)
 *       ->compile();
 */
final class TestingModule
{
    /** @var array<string, string|object> */
    private array $overrides = [];

    private function __construct(
        private readonly string $moduleClass,
    ) {}

    public static function create(string $moduleClass): self
    {
        return new self($moduleClass);
    }

    /**
     * Override a provider binding for testing.
     *
     * @param string $abstract The original provider class/interface
     * @param string|object $concrete The replacement class or instance
     */
    public function overrideProvider(string $abstract, string|object $concrete): self
    {
        $this->overrides[$abstract] = $concrete;

        return $this;
    }

    /**
     * @return array<string, string|object>
     */
    public function getOverrides(): array
    {
        return $this->overrides;
    }

    public function getModuleClass(): string
    {
        return $this->moduleClass;
    }

    /**
     * Compile the test module into a container with all overrides applied.
     */
    public function compile(): TestContainer
    {
        $container = new TestContainer($this->overrides);

        return $container;
    }
}
