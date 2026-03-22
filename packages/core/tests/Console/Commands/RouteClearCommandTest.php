<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Console\Commands;

use Lattice\Core\Console\Commands\RouteClearCommand;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RouteClearCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lattice_route_clear_test_' . uniqid();
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
        $command = new RouteClearCommand($this->tempDir);

        $this->assertSame('route:clear', $command->getName());
        $this->assertSame('Remove the route cache file', $command->getDescription());
    }

    #[Test]
    public function it_removes_route_cache_file(): void
    {
        $cacheDir = $this->tempDir . '/bootstrap/cache';
        mkdir($cacheDir, 0755, true);
        file_put_contents($cacheDir . '/routes.php', '<?php return [];');

        $command = new RouteClearCommand($this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Route cache cleared successfully', $tester->getDisplay());
        $this->assertFileDoesNotExist($cacheDir . '/routes.php');
    }

    #[Test]
    public function it_handles_missing_cache_file_gracefully(): void
    {
        $command = new RouteClearCommand($this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertStringContainsString('Route cache file does not exist', $tester->getDisplay());
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
