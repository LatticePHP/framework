<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Console\Commands;

use Lattice\Core\Config\ConfigRepository;
use Lattice\Core\Console\Commands\ConfigCacheCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigCacheCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lattice_config_cache_test_' . uniqid();
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
        $config = new ConfigRepository();
        $command = new ConfigCacheCommand($config, $this->tempDir);

        $this->assertSame('config:cache', $command->getName());
        $this->assertSame('Create a configuration cache file for faster configuration loading', $command->getDescription());
    }

    #[Test]
    public function it_caches_config_to_file(): void
    {
        $config = new ConfigRepository([
            'app' => [
                'name' => 'LatticePHP',
                'env' => 'production',
            ],
            'database' => [
                'driver' => 'mysql',
                'host' => 'localhost',
            ],
        ]);

        $command = new ConfigCacheCommand($config, $this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Configuration cached successfully', $tester->getDisplay());

        $cachePath = $this->tempDir . '/bootstrap/cache/config.php';
        $this->assertFileExists($cachePath);

        $cached = require $cachePath;
        $this->assertIsArray($cached);
        $this->assertSame('LatticePHP', $cached['app']['name']);
        $this->assertSame('mysql', $cached['database']['driver']);
    }

    #[Test]
    public function it_creates_cache_directory_if_missing(): void
    {
        $config = new ConfigRepository();
        $command = new ConfigCacheCommand($config, $this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryExists($this->tempDir . '/bootstrap/cache');
    }

    #[Test]
    public function it_caches_empty_config(): void
    {
        $config = new ConfigRepository();
        $command = new ConfigCacheCommand($config, $this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());

        $cachePath = $this->tempDir . '/bootstrap/cache/config.php';
        $cached = require $cachePath;
        $this->assertSame([], $cached);
    }

    #[Test]
    public function it_displays_key_count(): void
    {
        $config = new ConfigRepository([
            'app' => ['name' => 'Test'],
            'cache' => ['driver' => 'file'],
            'mail' => ['from' => 'test@test.com'],
        ]);

        $command = new ConfigCacheCommand($config, $this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertStringContainsString('Keys', $tester->getDisplay());
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
