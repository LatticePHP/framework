<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Unit;

use Lattice\Compiler\Discovery\ModuleMetadata;
use Lattice\Compiler\Graph\ModuleGraph;
use Lattice\Compiler\Graph\ModuleGraphBuilder;
use Lattice\Compiler\Manifest\ManifestCompiler;
use Lattice\Compiler\Manifest\ManifestLoader;
use Lattice\Compiler\Tests\Fixtures\ConfigService;
use Lattice\Compiler\Tests\Fixtures\DefaultController;
use Lattice\Compiler\Tests\Fixtures\GlobalConfigModule;
use Lattice\Compiler\Tests\Fixtures\SimpleService;
use Lattice\Compiler\Tests\Fixtures\UserController;
use Lattice\Compiler\Tests\Fixtures\UserModule;
use Lattice\Compiler\Tests\Fixtures\UserService;
use Lattice\Compiler\Tests\Fixtures\AppModule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ManifestCompilerTest extends TestCase
{
    private string $outputDir;

    protected function setUp(): void
    {
        $this->outputDir = sys_get_temp_dir() . '/lattice_manifest_test_' . uniqid();
        mkdir($this->outputDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->outputDir);
    }

    #[Test]
    public function it_compiles_manifest_to_file(): void
    {
        $graph = $this->buildSampleGraph();
        $compiler = new ManifestCompiler();
        $outputPath = $this->outputDir . '/modules.php';

        $compiler->compile($graph, $outputPath);

        self::assertFileExists($outputPath);

        // The manifest should be a valid PHP file returning an array
        $data = require $outputPath;
        self::assertIsArray($data);
        self::assertArrayHasKey('modules', $data);
        self::assertArrayHasKey('topological_order', $data);
    }

    #[Test]
    public function it_loads_compiled_manifest(): void
    {
        $originalGraph = $this->buildSampleGraph();
        $compiler = new ManifestCompiler();
        $outputPath = $this->outputDir . '/modules.php';

        $compiler->compile($originalGraph, $outputPath);

        $loader = new ManifestLoader();
        $loadedGraph = $loader->load($outputPath);

        self::assertInstanceOf(ModuleGraph::class, $loadedGraph);
        self::assertCount(
            count($originalGraph->getModules()),
            $loadedGraph->getModules(),
        );

        // Verify topological order is preserved
        self::assertSame(
            $originalGraph->getTopologicalOrder(),
            $loadedGraph->getTopologicalOrder(),
        );
    }

    #[Test]
    public function it_checks_manifest_validity(): void
    {
        $graph = $this->buildSampleGraph();
        $compiler = new ManifestCompiler();
        $outputPath = $this->outputDir . '/modules.php';

        $compiler->compile($graph, $outputPath);

        $loader = new ManifestLoader();
        self::assertTrue($loader->isValid($outputPath));
    }

    #[Test]
    public function it_reports_invalid_for_missing_manifest(): void
    {
        $loader = new ManifestLoader();
        self::assertFalse($loader->isValid($this->outputDir . '/nonexistent.php'));
    }

    #[Test]
    public function it_preserves_global_flag_in_manifest(): void
    {
        $builder = new ModuleGraphBuilder();

        $builder->addModule(GlobalConfigModule::class, new ModuleMetadata(
            imports: [],
            providers: [ConfigService::class],
            controllers: [],
            exports: [ConfigService::class],
            isGlobal: true,
        ));

        $graph = $builder->build();
        $compiler = new ManifestCompiler();
        $outputPath = $this->outputDir . '/modules.php';

        $compiler->compile($graph, $outputPath);

        $loader = new ManifestLoader();
        $loaded = $loader->load($outputPath);

        self::assertTrue($loaded->isGlobal(GlobalConfigModule::class));
    }

    private function buildSampleGraph(): ModuleGraph
    {
        $builder = new ModuleGraphBuilder();

        $builder->addModule(UserModule::class, new ModuleMetadata(
            imports: [],
            providers: [UserService::class],
            controllers: [UserController::class],
            exports: [UserService::class],
            isGlobal: false,
        ));

        $builder->addModule(AppModule::class, new ModuleMetadata(
            imports: [UserModule::class],
            providers: [SimpleService::class],
            controllers: [DefaultController::class],
            exports: [SimpleService::class],
            isGlobal: false,
        ));

        return $builder->build();
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
