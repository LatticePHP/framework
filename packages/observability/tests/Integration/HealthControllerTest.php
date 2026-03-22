<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Integration;

use Lattice\Observability\Health\HealthCheckInterface;
use Lattice\Observability\Health\HealthController;
use Lattice\Observability\Health\HealthRegistry;
use Lattice\Observability\Health\HealthResult;
use Lattice\Observability\Health\HealthStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HealthControllerTest extends TestCase
{
    #[Test]
    public function test_check_returns_status_array(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('database', HealthStatus::Up, 'Connected'));

        $controller = new HealthController($registry);
        $result = $controller->check();

        $this->assertIsArray($result);
        $this->assertSame('up', $result['status']);
        $this->assertArrayHasKey('checks', $result);
        $this->assertArrayHasKey('database', $result['checks']);
    }

    #[Test]
    public function test_liveness_returns_up(): void
    {
        $registry = new HealthRegistry();
        $controller = new HealthController($registry);

        $result = $controller->liveness();

        $this->assertSame(['status' => 'up'], $result);
    }

    #[Test]
    public function test_readiness_returns_status_when_healthy(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('database', HealthStatus::Up, 'OK'));
        $registry->register($this->createCheck('cache', HealthStatus::Up, 'OK'));

        $controller = new HealthController($registry);
        $result = $controller->readiness();

        $this->assertSame('up', $result['status']);
    }

    #[Test]
    public function test_readiness_throws_when_unhealthy(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('database', HealthStatus::Down, 'Connection refused'));

        $controller = new HealthController($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service not ready');

        $controller->readiness();
    }

    #[Test]
    public function test_readiness_throws_when_degraded(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('cache', HealthStatus::Degraded, 'High latency'));

        $controller = new HealthController($registry);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Service not ready');

        $controller->readiness();
    }

    #[Test]
    public function test_check_with_no_checks_registered(): void
    {
        $registry = new HealthRegistry();
        $controller = new HealthController($registry);

        $result = $controller->check();

        $this->assertSame('up', $result['status']);
        $this->assertEmpty($result['checks']);
    }

    #[Test]
    public function test_check_aggregates_multiple_checks(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('database', HealthStatus::Up, 'Connected'));
        $registry->register($this->createCheck('cache', HealthStatus::Up, 'Available'));
        $registry->register($this->createCheck('queue', HealthStatus::Up, 'Running'));

        $controller = new HealthController($registry);
        $result = $controller->check();

        $this->assertSame('up', $result['status']);
        $this->assertCount(3, $result['checks']);
        $this->assertArrayHasKey('database', $result['checks']);
        $this->assertArrayHasKey('cache', $result['checks']);
        $this->assertArrayHasKey('queue', $result['checks']);
    }

    #[Test]
    public function test_check_reflects_down_status(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('database', HealthStatus::Up, 'Connected'));
        $registry->register($this->createCheck('cache', HealthStatus::Down, 'Unreachable'));

        $controller = new HealthController($registry);
        $result = $controller->check();

        $this->assertSame('down', $result['status']);
    }

    private function createCheck(string $name, HealthStatus $status, string $message): HealthCheckInterface
    {
        return new class($name, $status, $message) implements HealthCheckInterface {
            public function __construct(
                private readonly string $name,
                private readonly HealthStatus $status,
                private readonly string $message,
            ) {}

            public function getName(): string
            {
                return $this->name;
            }

            public function check(): HealthResult
            {
                return new HealthResult(
                    status: $this->status,
                    message: $this->message,
                );
            }
        };
    }
}
