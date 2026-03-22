<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Console\Commands;

use Lattice\Core\Console\Commands\OpenApiGenerateCommand;
use Lattice\OpenApi\OpenApiGenerator;
use Lattice\OpenApi\Schema\SchemaGenerator;
use Lattice\Routing\RouteDefinition;
use Lattice\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class OpenApiGenerateCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lattice_openapi_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function it_constructs_with_correct_name(): void
    {
        $router = new Router();
        $generator = new OpenApiGenerator('Test API', '1.0.0', new SchemaGenerator());
        $command = new OpenApiGenerateCommand($router, $generator, $this->tempDir);

        $this->assertSame('openapi:generate', $command->getName());
        $this->assertSame('Generate OpenAPI 3.1 specification from route metadata', $command->getDescription());
    }

    #[Test]
    public function it_has_correct_options(): void
    {
        $router = new Router();
        $generator = new OpenApiGenerator('Test API', '1.0.0', new SchemaGenerator());
        $command = new OpenApiGenerateCommand($router, $generator, $this->tempDir);
        $def = $command->getDefinition();

        $this->assertTrue($def->hasOption('output'));
        $this->assertTrue($def->hasOption('format'));
        $this->assertTrue($def->hasOption('stdout'));
        $this->assertSame('json', $def->getOption('format')->getDefault());
    }

    #[Test]
    public function it_generates_json_spec_to_file(): void
    {
        $router = new Router();
        $generator = new OpenApiGenerator('Test API', '1.0.0', new SchemaGenerator());
        $command = new OpenApiGenerateCommand($router, $generator, $this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('OpenAPI specification generated successfully', $tester->getDisplay());

        $outputPath = $this->tempDir . '/openapi.json';
        $this->assertFileExists($outputPath);

        $content = json_decode(file_get_contents($outputPath), true);
        $this->assertSame('3.1.0', $content['openapi']);
        $this->assertSame('Test API', $content['info']['title']);
        $this->assertSame('1.0.0', $content['info']['version']);
    }

    #[Test]
    public function it_generates_yaml_spec_to_file(): void
    {
        $router = new Router();
        $generator = new OpenApiGenerator('Test API', '1.0.0', new SchemaGenerator());
        $command = new OpenApiGenerateCommand($router, $generator, $this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute(['--format' => 'yaml']);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $outputPath = $this->tempDir . '/openapi.yaml';
        $this->assertFileExists($outputPath);

        $content = file_get_contents($outputPath);
        $this->assertStringContainsString('openapi: 3.1.0', $content);
        $this->assertStringContainsString('Test API', $content);
    }

    #[Test]
    public function it_outputs_to_stdout(): void
    {
        $router = new Router();
        $generator = new OpenApiGenerator('Test API', '1.0.0', new SchemaGenerator());
        $command = new OpenApiGenerateCommand($router, $generator, $this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute(['--stdout' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $display = $tester->getDisplay();
        $content = json_decode($display, true);
        $this->assertSame('3.1.0', $content['openapi']);
        // Should not contain banner/header when stdout
        $this->assertStringNotContainsString('OpenAPI Generator', $display);
    }

    #[Test]
    public function it_writes_to_custom_output_path(): void
    {
        $router = new Router();
        $generator = new OpenApiGenerator('Test API', '1.0.0', new SchemaGenerator());
        $command = new OpenApiGenerateCommand($router, $generator, $this->tempDir);
        $tester = new CommandTester($command);

        $customPath = $this->tempDir . '/docs/api-spec.json';
        $tester->execute(['--output' => $customPath]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileExists($customPath);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS);
        $files = new \RecursiveIteratorIterator($iterator, \RecursiveIteratorIterator::CHILD_FIRST);

        foreach ($files as $file) {
            if ($file->isDir()) {
                rmdir($file->getRealPath());
            } else {
                unlink($file->getRealPath());
            }
        }

        rmdir($dir);
    }
}
