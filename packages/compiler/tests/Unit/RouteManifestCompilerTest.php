<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Unit;

use Lattice\Compiler\Discovery\ControllerMetadata;
use Lattice\Compiler\Manifest\RouteManifestCompiler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RouteManifestCompilerTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/lattice_route_manifest_test_' . uniqid();
        mkdir($this->outputDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
    }

    #[Test]
    public function it_compiles_route_metadata(): void
    {
        $controllers = [
            new ControllerMetadata(
                className: 'App\\Controller\\UserController',
                prefix: '/users',
            ),
            new ControllerMetadata(
                className: 'App\\Controller\\PostController',
                prefix: '/posts',
            ),
        ];

        $compiler = new RouteManifestCompiler();
        $outputPath = $this->outputDir . '/routes.php';

        $compiler->compile($controllers, $outputPath);

        self::assertFileExists($outputPath);

        $data = require $outputPath;
        self::assertIsArray($data);
        self::assertCount(2, $data);
    }

    #[Test]
    public function it_preserves_controller_class_names(): void
    {
        $controllers = [
            new ControllerMetadata(
                className: 'App\\Controller\\UserController',
                prefix: '/users',
            ),
        ];

        $compiler = new RouteManifestCompiler();
        $outputPath = $this->outputDir . '/routes.php';

        $compiler->compile($controllers, $outputPath);

        $data = require $outputPath;

        self::assertSame('App\\Controller\\UserController', $data[0]['className']);
        self::assertSame('/users', $data[0]['prefix']);
    }

    #[Test]
    public function it_handles_empty_prefix(): void
    {
        $controllers = [
            new ControllerMetadata(
                className: 'App\\Controller\\HomeController',
                prefix: '',
            ),
        ];

        $compiler = new RouteManifestCompiler();
        $outputPath = $this->outputDir . '/routes.php';

        $compiler->compile($controllers, $outputPath);

        $data = require $outputPath;

        self::assertSame('', $data[0]['prefix']);
    }

    #[Test]
    public function it_handles_empty_controller_list(): void
    {
        $compiler = new RouteManifestCompiler();
        $outputPath = $this->outputDir . '/routes.php';

        $compiler->compile([], $outputPath);

        $data = require $outputPath;
        self::assertIsArray($data);
        self::assertEmpty($data);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
