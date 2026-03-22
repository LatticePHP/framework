<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Tests\Console;

use Lattice\Catalyst\Console\CatalystInstallCommand;
use Lattice\Catalyst\Guidelines\GuidelineGenerator;
use Lattice\Catalyst\Guidelines\GuidelineRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CatalystInstallCommandTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lattice_catalyst_install_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        // Create a minimal composer.json
        file_put_contents(
            $this->tempDir . '/composer.json',
            (string) json_encode([
                'require' => [
                    'lattice/core' => '^1.0',
                    'lattice/routing' => '^1.0',
                ],
            ]),
        );
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function test_command_has_correct_name(): void
    {
        $command = new CatalystInstallCommand();

        $this->assertSame('catalyst:install', $command->getName());
    }

    #[Test]
    public function test_install_generates_claude_md(): void
    {
        $registry = new GuidelineRegistry();
        $registry->register('core', '## Core\n\nCore guidelines.');
        $generator = new GuidelineGenerator($registry);

        $command = new CatalystInstallCommand($this->tempDir, $generator);
        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileExists($this->tempDir . '/CLAUDE.md');

        $content = (string) file_get_contents($this->tempDir . '/CLAUDE.md');
        $this->assertStringContainsString('CLAUDE.md', $content);
        $this->assertStringContainsString('lattice/core', $content);
    }

    #[Test]
    public function test_install_generates_mcp_json(): void
    {
        $command = new CatalystInstallCommand($this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileExists($this->tempDir . '/.mcp.json');

        $content = (string) file_get_contents($this->tempDir . '/.mcp.json');
        $decoded = json_decode($content, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('mcpServers', $decoded);
    }

    #[Test]
    public function test_install_creates_ai_directories(): void
    {
        $command = new CatalystInstallCommand($this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertDirectoryExists($this->tempDir . '/.ai/guidelines');
        $this->assertDirectoryExists($this->tempDir . '/.ai/skills');
    }

    #[Test]
    public function test_dry_run_does_not_write_files(): void
    {
        $command = new CatalystInstallCommand($this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute(['--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $tester->getStatusCode());
        $this->assertFileDoesNotExist($this->tempDir . '/CLAUDE.md');
        $this->assertFileDoesNotExist($this->tempDir . '/.mcp.json');

        $display = $tester->getDisplay();
        $this->assertStringContainsString('dry-run', $display);
    }

    #[Test]
    public function test_install_shows_package_count(): void
    {
        $command = new CatalystInstallCommand($this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute(['--force' => true]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('2', $display); // 2 lattice packages
    }

    #[Test]
    public function test_install_without_force_warns_about_existing_files(): void
    {
        // Create existing CLAUDE.md
        file_put_contents($this->tempDir . '/CLAUDE.md', 'existing content');

        $command = new CatalystInstallCommand($this->tempDir);
        $tester = new CommandTester($command);
        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('already exists', $display);
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
