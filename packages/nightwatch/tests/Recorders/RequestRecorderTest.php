<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Tests\Recorders;

use Lattice\Nightwatch\Recorders\RequestRecorder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequestRecorderTest extends TestCase
{
    #[Test]
    public function test_record_and_retrieve_metrics(): void
    {
        $recorder = new RequestRecorder();

        $recorder->record(['endpoint' => 'GET /api/users', 'duration_ms' => 50, 'status' => 200]);
        $recorder->record(['endpoint' => 'GET /api/users', 'duration_ms' => 100, 'status' => 200]);
        $recorder->record(['endpoint' => 'GET /api/users', 'duration_ms' => 150, 'status' => 404]);

        $metrics = $recorder->getMetrics();

        $this->assertSame(3, $metrics['total_requests']);
        $this->assertArrayHasKey('GET /api/users', $metrics['endpoints']);

        $endpoint = $metrics['endpoints']['GET /api/users'];
        $this->assertSame(3, $endpoint['count']);
        $this->assertSame(50.0, $endpoint['min']);
        $this->assertSame(150.0, $endpoint['max']);
        $this->assertEqualsWithDelta(100.0, $endpoint['avg'], 0.01);
    }

    #[Test]
    public function test_percentile_calculation(): void
    {
        $recorder = new RequestRecorder();

        // Add 100 requests with known latencies
        for ($i = 1; $i <= 100; $i++) {
            $recorder->record([
                'endpoint' => 'GET /api/test',
                'duration_ms' => (float) $i,
                'status' => 200,
            ]);
        }

        $metrics = $recorder->getMetrics();
        $endpoint = $metrics['endpoints']['GET /api/test'];

        $this->assertEqualsWithDelta(50.0, $endpoint['p50'], 1.0);
        $this->assertEqualsWithDelta(95.0, $endpoint['p95'], 1.0);
        $this->assertEqualsWithDelta(99.0, $endpoint['p99'], 1.0);
    }

    #[Test]
    public function test_status_code_distribution(): void
    {
        $recorder = new RequestRecorder();

        $recorder->record(['endpoint' => 'GET /api/test', 'duration_ms' => 10, 'status' => 200]);
        $recorder->record(['endpoint' => 'GET /api/test', 'duration_ms' => 20, 'status' => 200]);
        $recorder->record(['endpoint' => 'GET /api/test', 'duration_ms' => 30, 'status' => 404]);
        $recorder->record(['endpoint' => 'GET /api/test', 'duration_ms' => 40, 'status' => 500]);

        $metrics = $recorder->getMetrics();
        $statusCodes = $metrics['endpoints']['GET /api/test']['status_codes'];

        $this->assertSame(2, $statusCodes[200]);
        $this->assertSame(1, $statusCodes[404]);
        $this->assertSame(1, $statusCodes[500]);
    }

    #[Test]
    public function test_endpoint_grouping(): void
    {
        $recorder = new RequestRecorder();

        $recorder->record(['endpoint' => 'GET /api/users', 'duration_ms' => 50, 'status' => 200]);
        $recorder->record(['endpoint' => 'POST /api/users', 'duration_ms' => 100, 'status' => 201]);
        $recorder->record(['endpoint' => 'GET /api/posts', 'duration_ms' => 75, 'status' => 200]);

        $metrics = $recorder->getMetrics();

        $this->assertSame(3, $metrics['total_requests']);
        $this->assertCount(3, $metrics['endpoints']);
        $this->assertArrayHasKey('GET /api/users', $metrics['endpoints']);
        $this->assertArrayHasKey('POST /api/users', $metrics['endpoints']);
        $this->assertArrayHasKey('GET /api/posts', $metrics['endpoints']);
    }

    #[Test]
    public function test_reset_clears_metrics(): void
    {
        $recorder = new RequestRecorder();

        $recorder->record(['endpoint' => 'GET /test', 'duration_ms' => 50, 'status' => 200]);
        $this->assertSame(1, $recorder->getMetrics()['total_requests']);

        $recorder->reset();

        $metrics = $recorder->getMetrics();
        $this->assertSame(0, $metrics['total_requests']);
        $this->assertEmpty($metrics['endpoints']);
    }

    #[Test]
    public function test_disabled_recorder_does_not_record(): void
    {
        $recorder = new RequestRecorder();
        $recorder->setEnabled(false);

        $recorder->record(['endpoint' => 'GET /test', 'duration_ms' => 50, 'status' => 200]);

        $metrics = $recorder->getMetrics();
        $this->assertSame(0, $metrics['total_requests']);
    }

    #[Test]
    public function test_single_entry_percentiles(): void
    {
        $recorder = new RequestRecorder();

        $recorder->record(['endpoint' => 'GET /single', 'duration_ms' => 42.0, 'status' => 200]);

        $metrics = $recorder->getMetrics();
        $endpoint = $metrics['endpoints']['GET /single'];

        $this->assertSame(42.0, $endpoint['p50']);
        $this->assertSame(42.0, $endpoint['p95']);
        $this->assertSame(42.0, $endpoint['p99']);
    }

    #[Test]
    public function test_empty_metrics(): void
    {
        $recorder = new RequestRecorder();

        $metrics = $recorder->getMetrics();

        $this->assertSame(0, $metrics['total_requests']);
        $this->assertEmpty($metrics['endpoints']);
    }
}
