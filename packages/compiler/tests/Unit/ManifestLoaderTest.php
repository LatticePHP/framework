<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Unit;

use Lattice\Compiler\Exceptions\StaleManifestException;
use Lattice\Compiler\Graph\ModuleGraph;
use Lattice\Compiler\Manifest\ManifestLoader;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManifestLoaderTest extends TestCase
{
    private ManifestLoader $loader;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->loader = new ManifestLoader();
        $this->tempDir = sys_get_temp_dir() . '/lattice_manifest_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $files = glob($this->tempDir . '/*');
        if ($files !== false) {
            foreach ($files as $file) {
                unlink($file);
            }
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
    }

    #[Test]
    public function test_load_valid_manifest(): void
    {
        $path = $this->tempDir . '/manifest.php';
        file_put_contents($path, '<?php return ' . var_export([
            'modules' => [
                'App\\UserModule' => [
                    'className' => 'App\\UserModule',
                    'imports' => [],
                    'providers' => ['App\\UserService'],
                    'controllers' => ['App\\UserController'],
                    'exports' => ['App\\UserService'],
                    'isGlobal' => false,
                ],
            ],
            'topological_order' => ['App\\UserModule'],
            'compiled_at' => '2025-01-01 00:00:00',
        ], true) . ';');

        $graph = $this->loader->load($path);

        self::assertInstanceOf(ModuleGraph::class, $graph);
        self::assertCount(1, $graph->getModules());
        self::assertSame(['App\\UserModule'], $graph->getTopologicalOrder());

        $node = $graph->getModule('App\\UserModule');
        self::assertSame('App\\UserModule', $node->className);
        self::assertSame(['App\\UserService'], $node->providers);
    }

    #[Test]
    public function test_is_valid_returns_true_for_valid_manifest(): void
    {
        $path = $this->tempDir . '/valid.php';
        file_put_contents($path, '<?php return ' . var_export([
            'modules' => [],
            'topological_order' => [],
            'compiled_at' => '2025-01-01 00:00:00',
        ], true) . ';');

        self::assertTrue($this->loader->isValid($path));
    }

    #[Test]
    public function test_is_valid_returns_false_for_missing_file(): void
    {
        self::assertFalse($this->loader->isValid($this->tempDir . '/nonexistent.php'));
    }

    #[Test]
    public function test_is_valid_returns_false_for_missing_keys(): void
    {
        $path = $this->tempDir . '/incomplete.php';
        file_put_contents($path, '<?php return ' . var_export([
            'modules' => [],
        ], true) . ';');

        self::assertFalse($this->loader->isValid($path));
    }

    #[Test]
    public function test_load_throws_for_missing_file(): void
    {
        $path = $this->tempDir . '/nonexistent.php';

        $this->expectException(StaleManifestException::class);

        $this->loader->load($path);
    }

    #[Test]
    public function test_load_throws_for_invalid_manifest(): void
    {
        $path = $this->tempDir . '/invalid.php';
        file_put_contents($path, '<?php return ' . var_export([
            'modules' => [],
        ], true) . ';');

        $this->expectException(StaleManifestException::class);

        $this->loader->load($path);
    }
}
