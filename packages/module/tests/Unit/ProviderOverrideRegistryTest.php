<?php

declare(strict_types=1);

namespace Lattice\Module\Tests\Unit;

use Lattice\Module\Provider\ProviderOverrideRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProviderOverrideRegistry::class)]
final class ProviderOverrideRegistryTest extends TestCase
{
    private ProviderOverrideRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ProviderOverrideRegistry();
    }

    #[Test]
    public function it_registers_an_override(): void
    {
        $this->registry->override('App\\UserService', 'App\\MockUserService');

        self::assertTrue($this->registry->hasOverride('App\\UserService'));
        self::assertSame('App\\MockUserService', $this->registry->getOverride('App\\UserService'));
    }

    #[Test]
    public function it_overrides_with_a_closure(): void
    {
        $factory = fn () => new \stdClass();

        $this->registry->override('App\\UserService', $factory);

        self::assertTrue($this->registry->hasOverride('App\\UserService'));
        self::assertSame($factory, $this->registry->getOverride('App\\UserService'));
    }

    #[Test]
    public function it_overrides_with_an_object_instance(): void
    {
        $mock = new \stdClass();
        $mock->name = 'mock';

        $this->registry->override('App\\UserService', $mock);

        self::assertSame($mock, $this->registry->getOverride('App\\UserService'));
    }

    #[Test]
    public function it_clears_all_overrides(): void
    {
        $this->registry->override('App\\UserService', 'App\\MockUserService');
        $this->registry->override('App\\LoggerService', 'App\\MockLoggerService');

        $this->registry->clearOverrides();

        self::assertFalse($this->registry->hasOverride('App\\UserService'));
        self::assertFalse($this->registry->hasOverride('App\\LoggerService'));
    }

    #[Test]
    public function it_returns_null_for_non_overridden_abstract(): void
    {
        self::assertNull($this->registry->getOverride('App\\NonExistent'));
    }

    #[Test]
    public function it_returns_false_for_non_overridden_has_check(): void
    {
        self::assertFalse($this->registry->hasOverride('App\\NonExistent'));
    }

    #[Test]
    public function it_allows_replacing_an_existing_override(): void
    {
        $this->registry->override('App\\UserService', 'App\\MockV1');
        $this->registry->override('App\\UserService', 'App\\MockV2');

        self::assertSame('App\\MockV2', $this->registry->getOverride('App\\UserService'));
    }

    #[Test]
    public function it_returns_all_overrides(): void
    {
        $this->registry->override('App\\A', 'App\\MockA');
        $this->registry->override('App\\B', 'App\\MockB');

        $all = $this->registry->allOverrides();

        self::assertCount(2, $all);
        self::assertSame('App\\MockA', $all['App\\A']);
        self::assertSame('App\\MockB', $all['App\\B']);
    }
}
