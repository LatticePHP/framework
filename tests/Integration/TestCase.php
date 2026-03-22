<?php

declare(strict_types=1);

namespace Tests\Integration;

use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case for integration tests.
 *
 * Boots the full framework stack and provides helpers for
 * cross-package integration testing.
 */
abstract class TestCase extends PHPUnitTestCase
{
    /**
     * Temporary directory for test artifacts (cache files, etc.).
     */
    protected string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tempDir = sys_get_temp_dir() . '/lattice_integration_' . bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        $this->cleanDirectory($this->tempDir);

        parent::tearDown();
    }

    /**
     * Recursively remove a directory and its contents.
     */
    private function cleanDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->cleanDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
