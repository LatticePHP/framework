<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Tests\Guidelines;

use Lattice\Catalyst\Guidelines\GuidelineGenerator;
use Lattice\Catalyst\Guidelines\GuidelineRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GuidelineGeneratorTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/lattice_catalyst_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
        parent::tearDown();
    }

    #[Test]
    public function test_detect_packages_from_composer_json(): void
    {
        $composerJson = [
            'require' => [
                'php' => '^8.4',
                'lattice/core' => '^1.0',
                'lattice/routing' => '^1.0',
            ],
            'require-dev' => [
                'lattice/testing' => '^1.0',
            ],
        ];

        file_put_contents(
            $this->tempDir . '/composer.json',
            (string) json_encode($composerJson),
        );

        $generator = new GuidelineGenerator();
        $packages = $generator->detectPackages($this->tempDir);

        $this->assertArrayHasKey('core', $packages);
        $this->assertArrayHasKey('routing', $packages);
        $this->assertArrayHasKey('testing', $packages);
        $this->assertSame('^1.0', $packages['core']);
    }

    #[Test]
    public function test_detect_packages_from_composer_lock(): void
    {
        $composerLock = [
            'packages' => [
                [
                    'name' => 'lattice/core',
                    'version' => 'v1.2.3',
                ],
                [
                    'name' => 'lattice/database',
                    'version' => 'v1.0.0',
                ],
                [
                    'name' => 'symfony/console',
                    'version' => 'v7.0.0',
                ],
            ],
            'packages-dev' => [
                [
                    'name' => 'lattice/testing',
                    'version' => 'v1.0.0',
                ],
            ],
        ];

        file_put_contents(
            $this->tempDir . '/composer.lock',
            (string) json_encode($composerLock),
        );

        $generator = new GuidelineGenerator();
        $packages = $generator->detectPackages($this->tempDir);

        $this->assertArrayHasKey('core', $packages);
        $this->assertArrayHasKey('database', $packages);
        $this->assertArrayHasKey('testing', $packages);
        $this->assertArrayNotHasKey('console', $packages);
        $this->assertSame('v1.2.3', $packages['core']);
    }

    #[Test]
    public function test_detect_packages_returns_empty_when_no_composer_files(): void
    {
        $generator = new GuidelineGenerator();
        $packages = $generator->detectPackages($this->tempDir);

        $this->assertSame([], $packages);
    }

    #[Test]
    public function test_generate_claude_md_with_packages(): void
    {
        $registry = new GuidelineRegistry();
        $registry->register('core', '## Core Package\n\nUse strict_types.');
        $registry->register('routing', '## Routing Package\n\nUse #[Get] attributes.');

        $generator = new GuidelineGenerator($registry);

        $packages = ['core' => '1.0.0', 'routing' => '1.0.0'];
        $content = $generator->generateClaudeMd($packages);

        $this->assertStringContainsString('CLAUDE.md', $content);
        $this->assertStringContainsString('LatticePHP', $content);
        $this->assertStringContainsString('lattice/core', $content);
        $this->assertStringContainsString('lattice/routing', $content);
        $this->assertStringContainsString('Core Package', $content);
        $this->assertStringContainsString('Routing Package', $content);
        $this->assertStringContainsString('**Installed Packages:** 2', $content);
    }

    #[Test]
    public function test_generate_claude_md_with_no_packages(): void
    {
        $generator = new GuidelineGenerator();
        $content = $generator->generateClaudeMd([]);

        $this->assertStringContainsString('CLAUDE.md', $content);
        $this->assertStringContainsString('**Installed Packages:** 0', $content);
    }

    #[Test]
    public function test_generate_claude_md_includes_project_instructions(): void
    {
        $guidelinesDir = $this->tempDir . '/.ai/guidelines';
        mkdir($guidelinesDir, 0755, true);
        file_put_contents($guidelinesDir . '/project.md', 'Always use PSR-12 code style.');

        $generator = new GuidelineGenerator();
        $content = $generator->generateClaudeMd([], $this->tempDir);

        $this->assertStringContainsString('Project-Specific Instructions', $content);
        $this->assertStringContainsString('Always use PSR-12 code style.', $content);
    }

    #[Test]
    public function test_generate_mcp_json(): void
    {
        $generator = new GuidelineGenerator();
        $json = $generator->generateMcpJson('/home/user/project');

        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('mcpServers', $decoded);
        $this->assertArrayHasKey('lattice-catalyst', $decoded['mcpServers']);
        $this->assertSame('php', $decoded['mcpServers']['lattice-catalyst']['command']);
        $this->assertContains('catalyst:mcp', $decoded['mcpServers']['lattice-catalyst']['args']);
    }

    #[Test]
    public function test_custom_guidelines_override_builtin(): void
    {
        $registry = new GuidelineRegistry();
        $registry->register('core', 'Builtin core guideline');

        // Custom override
        $customDir = $this->tempDir . '/.ai/guidelines';
        mkdir($customDir, 0755, true);
        file_put_contents($customDir . '/core.md', 'Custom core guideline');

        $generator = new GuidelineGenerator($registry);
        $generator->loadCustomGuidelines($this->tempDir);

        $this->assertSame('Custom core guideline', $generator->getRegistry()->get('core'));
    }

    #[Test]
    public function test_registry_loads_from_directory(): void
    {
        $guidelinesDir = $this->tempDir . '/guidelines';
        mkdir($guidelinesDir, 0755, true);
        file_put_contents($guidelinesDir . '/core.md', 'Core guideline content');
        file_put_contents($guidelinesDir . '/routing.md', 'Routing guideline content');

        $registry = new GuidelineRegistry();
        $registry->loadFromDirectory($guidelinesDir);

        $this->assertTrue($registry->has('core'));
        $this->assertTrue($registry->has('routing'));
        $this->assertSame('Core guideline content', $registry->get('core'));
        $this->assertSame(2, $registry->count());
    }

    #[Test]
    public function test_registry_returns_null_for_missing_package(): void
    {
        $registry = new GuidelineRegistry();

        $this->assertFalse($registry->has('nonexistent'));
        $this->assertNull($registry->get('nonexistent'));
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
