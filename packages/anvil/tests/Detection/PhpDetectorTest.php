<?php

declare(strict_types=1);

namespace Lattice\Anvil\Tests\Detection;

use Lattice\Anvil\Detection\DetectionResult;
use Lattice\Anvil\Detection\PhpDetector;
use PHPUnit\Framework\TestCase;

final class PhpDetectorTest extends TestCase
{
    public function test_detect_returns_detection_result(): void
    {
        $detector = new PhpDetector();
        $result = $detector->detect();

        $this->assertInstanceOf(DetectionResult::class, $result);
        $this->assertSame('PHP', $result->name);
    }

    public function test_php_is_always_installed_in_test_environment(): void
    {
        $detector = new PhpDetector();
        $result = $detector->detect();

        $this->assertTrue($result->installed);
        $this->assertNotNull($result->version);
        $this->assertSame('installed', $result->status);
    }

    public function test_php_version_matches_expected_format(): void
    {
        $detector = new PhpDetector();
        $result = $detector->detect();

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $result->version ?? '');
    }

    public function test_extensions_are_listed_in_details(): void
    {
        $detector = new PhpDetector();
        $result = $detector->detect();

        $this->assertArrayHasKey('extensions', $result->details);
        $this->assertIsArray($result->details['extensions']);
        $this->assertNotEmpty($result->details['extensions']);
    }

    public function test_common_extensions_are_present(): void
    {
        $detector = new PhpDetector();
        $result = $detector->detect();

        $extensions = $result->details['extensions'] ?? [];
        // json is always present in PHP 8.0+
        $this->assertContains('json', $extensions);
    }
}
