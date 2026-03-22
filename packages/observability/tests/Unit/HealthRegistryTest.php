<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Unit;

use Lattice\Observability\Health\HealthCheckInterface;
use Lattice\Observability\Health\HealthRegistry;
use Lattice\Observability\Health\HealthResult;
use Lattice\Observability\Health\HealthStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HealthRegistryTest extends TestCase
{
    private function createCheck(string $name, HealthStatus $status, string $message = ''): HealthCheckInterface
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

    #[Test]
    public function check_all_returns_empty_array_when_no_checks_registered(): void
    {
        $registry = new HealthRegistry();
        $this->assertSame([], $registry->checkAll());
    }

    #[Test]
    public function it_registers_and_runs_single_check(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('database', HealthStatus::Up, 'Connected'));

        $results = $registry->checkAll();

        $this->assertCount(1, $results);
        $this->assertArrayHasKey('database', $results);
        $this->assertSame(HealthStatus::Up, $results['database']->status);
        $this->assertSame('Connected', $results['database']->message);
    }

    #[Test]
    public function it_registers_and_runs_multiple_checks(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('database', HealthStatus::Up));
        $registry->register($this->createCheck('redis', HealthStatus::Degraded, 'Slow'));
        $registry->register($this->createCheck('queue', HealthStatus::Down, 'Disconnected'));

        $results = $registry->checkAll();

        $this->assertCount(3, $results);
        $this->assertSame(HealthStatus::Up, $results['database']->status);
        $this->assertSame(HealthStatus::Degraded, $results['redis']->status);
        $this->assertSame(HealthStatus::Down, $results['queue']->status);
    }

    #[Test]
    public function check_all_records_duration(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('database', HealthStatus::Up));

        $results = $registry->checkAll();

        $this->assertGreaterThanOrEqual(0.0, $results['database']->duration);
    }

    #[Test]
    public function check_all_handles_exception_as_down(): void
    {
        $failingCheck = new class implements HealthCheckInterface {
            public function getName(): string
            {
                return 'failing';
            }

            public function check(): HealthResult
            {
                throw new \RuntimeException('Connection refused');
            }
        };

        $registry = new HealthRegistry();
        $registry->register($failingCheck);

        $results = $registry->checkAll();

        $this->assertSame(HealthStatus::Down, $results['failing']->status);
        $this->assertSame('Connection refused', $results['failing']->message);
    }

    #[Test]
    public function to_array_returns_overall_up_when_all_up(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('db', HealthStatus::Up));
        $registry->register($this->createCheck('cache', HealthStatus::Up));

        $array = $registry->toArray();

        $this->assertSame('up', $array['status']);
        $this->assertCount(2, $array['checks']);
    }

    #[Test]
    public function to_array_returns_degraded_when_any_degraded(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('db', HealthStatus::Up));
        $registry->register($this->createCheck('cache', HealthStatus::Degraded));

        $array = $registry->toArray();

        $this->assertSame('degraded', $array['status']);
    }

    #[Test]
    public function to_array_returns_down_when_any_down(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('db', HealthStatus::Up));
        $registry->register($this->createCheck('cache', HealthStatus::Degraded));
        $registry->register($this->createCheck('queue', HealthStatus::Down));

        $array = $registry->toArray();

        $this->assertSame('down', $array['status']);
    }

    #[Test]
    public function to_array_check_entries_have_expected_structure(): void
    {
        $registry = new HealthRegistry();
        $registry->register($this->createCheck('db', HealthStatus::Up, 'OK'));

        $array = $registry->toArray();

        $this->assertArrayHasKey('status', $array['checks']['db']);
        $this->assertArrayHasKey('message', $array['checks']['db']);
        $this->assertArrayHasKey('metadata', $array['checks']['db']);
        $this->assertArrayHasKey('duration', $array['checks']['db']);
    }
}
