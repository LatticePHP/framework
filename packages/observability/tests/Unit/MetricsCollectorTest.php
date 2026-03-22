<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Unit;

use Lattice\Observability\Metrics\InMemoryMetricsCollector;
use Lattice\Observability\Metrics\MetricsCollector;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MetricsCollectorTest extends TestCase
{
    #[Test]
    public function it_implements_metrics_collector_interface(): void
    {
        $collector = new InMemoryMetricsCollector();
        $this->assertInstanceOf(MetricsCollector::class, $collector);
    }

    #[Test]
    public function it_starts_with_empty_metrics(): void
    {
        $collector = new InMemoryMetricsCollector();
        $this->assertSame([], $collector->getMetrics());
    }

    #[Test]
    public function counter_increments_by_default_value(): void
    {
        $collector = new InMemoryMetricsCollector();

        $collector->counter('http_requests_total');

        $metrics = $collector->getMetrics();
        $this->assertSame('counter', $metrics['http_requests_total']['type']);
        $this->assertSame(1.0, $metrics['http_requests_total']['entries'][0]['value']);
    }

    #[Test]
    public function counter_increments_with_custom_value(): void
    {
        $collector = new InMemoryMetricsCollector();

        $collector->counter('http_requests_total', 5);

        $metrics = $collector->getMetrics();
        $this->assertSame(5.0, $metrics['http_requests_total']['entries'][0]['value']);
    }

    #[Test]
    public function counter_accumulates_values_for_same_labels(): void
    {
        $collector = new InMemoryMetricsCollector();

        $collector->counter('http_requests_total', 1, ['method' => 'GET']);
        $collector->counter('http_requests_total', 3, ['method' => 'GET']);

        $metrics = $collector->getMetrics();
        $this->assertCount(1, $metrics['http_requests_total']['entries']);
        $this->assertSame(4.0, $metrics['http_requests_total']['entries'][0]['value']);
    }

    #[Test]
    public function counter_separates_different_labels(): void
    {
        $collector = new InMemoryMetricsCollector();

        $collector->counter('http_requests_total', 1, ['method' => 'GET']);
        $collector->counter('http_requests_total', 2, ['method' => 'POST']);

        $metrics = $collector->getMetrics();
        $this->assertCount(2, $metrics['http_requests_total']['entries']);
    }

    #[Test]
    public function gauge_sets_value(): void
    {
        $collector = new InMemoryMetricsCollector();

        $collector->gauge('cpu_usage', 45.5);

        $metrics = $collector->getMetrics();
        $this->assertSame('gauge', $metrics['cpu_usage']['type']);
        $this->assertSame(45.5, $metrics['cpu_usage']['entries'][0]['value']);
    }

    #[Test]
    public function gauge_replaces_value_for_same_labels(): void
    {
        $collector = new InMemoryMetricsCollector();

        $collector->gauge('cpu_usage', 45.5, ['host' => 'web-1']);
        $collector->gauge('cpu_usage', 78.2, ['host' => 'web-1']);

        $metrics = $collector->getMetrics();
        $this->assertCount(1, $metrics['cpu_usage']['entries']);
        $this->assertSame(78.2, $metrics['cpu_usage']['entries'][0]['value']);
    }

    #[Test]
    public function histogram_records_observations(): void
    {
        $collector = new InMemoryMetricsCollector();

        $collector->histogram('http_request_duration', 0.25);

        $metrics = $collector->getMetrics();
        $this->assertSame('histogram', $metrics['http_request_duration']['type']);
        $this->assertSame([0.25], $metrics['http_request_duration']['entries'][0]['values']);
        $this->assertSame(1, $metrics['http_request_duration']['entries'][0]['count']);
        $this->assertSame(0.25, $metrics['http_request_duration']['entries'][0]['sum']);
    }

    #[Test]
    public function histogram_accumulates_observations_for_same_labels(): void
    {
        $collector = new InMemoryMetricsCollector();

        $collector->histogram('http_request_duration', 0.25, ['path' => '/api']);
        $collector->histogram('http_request_duration', 0.50, ['path' => '/api']);
        $collector->histogram('http_request_duration', 0.10, ['path' => '/api']);

        $metrics = $collector->getMetrics();
        $entry = $metrics['http_request_duration']['entries'][0];
        $this->assertSame([0.25, 0.50, 0.10], $entry['values']);
        $this->assertSame(3, $entry['count']);
        $this->assertEqualsWithDelta(0.85, $entry['sum'], 0.0001);
    }

    #[Test]
    public function counter_stores_labels(): void
    {
        $collector = new InMemoryMetricsCollector();

        $collector->counter('http_requests_total', 1, ['method' => 'GET', 'status' => '200']);

        $metrics = $collector->getMetrics();
        $this->assertSame(['method' => 'GET', 'status' => '200'], $metrics['http_requests_total']['entries'][0]['labels']);
    }
}
