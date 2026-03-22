<?php

declare(strict_types=1);

namespace Lattice\Anvil\Tests\Detection;

use Lattice\Anvil\Detection\DetectionResult;
use Lattice\Anvil\Detection\DetectorInterface;
use Lattice\Anvil\Detection\SystemDetector;
use PHPUnit\Framework\TestCase;

final class SystemDetectorTest extends TestCase
{
    public function test_detect_all_returns_array_of_results(): void
    {
        $mockDetector1 = new class implements DetectorInterface {
            public function detect(): DetectionResult
            {
                return new DetectionResult(
                    name: 'Service A',
                    installed: true,
                    version: '1.0.0',
                    status: 'running',
                );
            }
        };

        $mockDetector2 = new class implements DetectorInterface {
            public function detect(): DetectionResult
            {
                return new DetectionResult(
                    name: 'Service B',
                    installed: false,
                );
            }
        };

        $system = new SystemDetector([$mockDetector1, $mockDetector2]);
        $results = $system->detectAll();

        $this->assertCount(2, $results);
        $this->assertSame('Service A', $results[0]->name);
        $this->assertTrue($results[0]->installed);
        $this->assertSame('1.0.0', $results[0]->version);
        $this->assertSame('running', $results[0]->status);

        $this->assertSame('Service B', $results[1]->name);
        $this->assertFalse($results[1]->installed);
        $this->assertNull($results[1]->version);
    }

    public function test_detect_all_with_default_detectors(): void
    {
        $system = new SystemDetector();
        $results = $system->detectAll();

        // Default detectors: Nginx, PHP, Node, Redis, MySQL, PostgreSQL, Docker
        $this->assertCount(7, $results);

        $names = array_map(fn(DetectionResult $r): string => $r->name, $results);
        $this->assertContains('Nginx', $names);
        $this->assertContains('PHP', $names);
        $this->assertContains('Node.js', $names);
        $this->assertContains('Redis', $names);
        $this->assertContains('MySQL', $names);
        $this->assertContains('PostgreSQL', $names);
        $this->assertContains('Docker', $names);
    }

    public function test_add_custom_detector(): void
    {
        $customDetector = new class implements DetectorInterface {
            public function detect(): DetectionResult
            {
                return new DetectionResult(
                    name: 'Custom Service',
                    installed: true,
                    version: '2.0.0',
                    status: 'running',
                );
            }
        };

        $system = new SystemDetector([]);
        $system->addDetector($customDetector);

        $results = $system->detectAll();
        $this->assertCount(1, $results);
        $this->assertSame('Custom Service', $results[0]->name);
    }

    public function test_detection_result_status_color(): void
    {
        $running = new DetectionResult(name: 'A', installed: true, status: 'running');
        $this->assertSame('green', $running->getStatusColor());

        $stopped = new DetectionResult(name: 'B', installed: true, status: 'stopped');
        $this->assertSame('red', $stopped->getStatusColor());

        $installed = new DetectionResult(name: 'C', installed: true, status: 'installed');
        $this->assertSame('yellow', $installed->getStatusColor());

        $unknown = new DetectionResult(name: 'D', installed: false);
        $this->assertSame('gray', $unknown->getStatusColor());
    }

    public function test_detection_result_status_label(): void
    {
        $running = new DetectionResult(name: 'A', installed: true, status: 'running');
        $this->assertSame('Running', $running->getStatusLabel());

        $stopped = new DetectionResult(name: 'B', installed: true, status: 'stopped');
        $this->assertSame('Stopped', $stopped->getStatusLabel());

        $notInstalled = new DetectionResult(name: 'C', installed: false);
        $this->assertSame('Not installed', $notInstalled->getStatusLabel());
    }

    public function test_empty_detectors_returns_empty_results(): void
    {
        $system = new SystemDetector([]);
        $results = $system->detectAll();

        $this->assertSame([], $results);
    }
}
