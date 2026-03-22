<?php

declare(strict_types=1);

namespace Lattice\RateLimit\Tests\Unit;

use Lattice\RateLimit\Attributes\RateLimit;
use Lattice\RateLimit\RateLimitGuard;
use Lattice\RateLimit\RateLimiter;
use Lattice\RateLimit\Store\InMemoryRateLimitStore;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[RateLimit(maxAttempts: 2, decaySeconds: 60)]
final class RateLimitedController
{
    public function index(): void {}

    #[RateLimit(maxAttempts: 1, decaySeconds: 30, key: 'custom-key')]
    public function limited(): void {}
}

final class UnlimitedController
{
    public function index(): void {}
}

#[CoversClass(RateLimitGuard::class)]
final class RateLimitGuardTest extends TestCase
{
    private RateLimitGuard $guard;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new RateLimiter(new InMemoryRateLimitStore());
        $this->guard = new RateLimitGuard($this->limiter);
    }

    #[Test]
    public function it_allows_when_under_limit(): void
    {
        $context = $this->createContext(RateLimitedController::class, 'index');

        $this->assertTrue($this->guard->canActivate($context));
    }

    #[Test]
    public function it_blocks_when_class_limit_exceeded(): void
    {
        $context = $this->createContext(RateLimitedController::class, 'index');

        $this->guard->canActivate($context); // 1
        $this->guard->canActivate($context); // 2

        $this->assertFalse($this->guard->canActivate($context)); // 3 -> blocked
    }

    #[Test]
    public function it_uses_method_attribute_over_class(): void
    {
        $context = $this->createContext(RateLimitedController::class, 'limited');

        $this->guard->canActivate($context); // 1

        $this->assertFalse($this->guard->canActivate($context)); // 2 -> blocked (limit is 1)
    }

    #[Test]
    public function it_allows_when_no_attribute_present(): void
    {
        $context = $this->createContext(UnlimitedController::class, 'index');

        $this->assertTrue($this->guard->canActivate($context));
    }

    private function createContext(string $class, string $method): ExecutionContextInterface
    {
        return new class($class, $method) implements ExecutionContextInterface {
            public function __construct(
                private readonly string $class,
                private readonly string $method,
            ) {}

            public function getType(): ExecutionType { return ExecutionType::Http; }
            public function getModule(): string { return 'test'; }
            public function getHandler(): string { return $this->class . '::' . $this->method; }
            public function getClass(): string { return $this->class; }
            public function getMethod(): string { return $this->method; }
            public function getCorrelationId(): string { return 'test-correlation-id'; }
            public function getPrincipal(): ?PrincipalInterface { return null; }
        };
    }
}
