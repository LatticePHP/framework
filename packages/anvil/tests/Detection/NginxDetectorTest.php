<?php

declare(strict_types=1);

namespace Lattice\Anvil\Tests\Detection;

use Lattice\Anvil\Detection\DetectionResult;
use Lattice\Anvil\Detection\NginxDetector;
use PHPUnit\Framework\TestCase;

final class NginxDetectorTest extends TestCase
{
    public function test_detect_returns_detection_result(): void
    {
        $detector = new NginxDetector();
        $result = $detector->detect();

        $this->assertInstanceOf(DetectionResult::class, $result);
        $this->assertSame('Nginx', $result->name);
        $this->assertIsBool($result->installed);
    }

    public function test_detection_result_has_correct_name(): void
    {
        $detector = new NginxDetector();
        $result = $detector->detect();

        $this->assertSame('Nginx', $result->name);
    }

    public function test_not_installed_result_has_null_version(): void
    {
        // On CI/test environments nginx is typically not installed
        $detector = new NginxDetector();
        $result = $detector->detect();

        if (!$result->installed) {
            $this->assertNull($result->version);
            $this->assertSame('unknown', $result->status);
        } else {
            $this->assertNotNull($result->version);
            $this->assertContains($result->status, ['running', 'stopped']);
        }
    }
}
