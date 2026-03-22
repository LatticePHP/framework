<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Console\Commands;

use Lattice\Core\Console\Commands\ConfigClearCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigClearCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lattice_config_clear_test_' . uniqid();
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
        $command = new ConfigClearCommand($this->tempDir);

        $this->assertSame('config:clear', $command->getName());
        $this->assertSame('Remove the configuration cache file', $command->getDescription());
    }

    #[Test]
    public function it_removes_config_cache_file(): void
    {
        $cacheDir = $this->tempDir . '/bootstrap/cache';
        mkdir($cacheDir, 0755, true);
        file_put_contents($cacheDir . '/config.php', '<?php return [];');

        $command = new ConfigClearCommand($this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Configuration cache cleared successfully', $tester->getDisplay());
        $this->assertFileDoesNotExist($cacheDir . '/config.php');
    }

    #[Test]
    public function it_handles_missing_cache_file_gracefully(): void
    {
        $command = new ConfigClearCommand($this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Configuration cache file does not exist', $tester->getDisplay());
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
