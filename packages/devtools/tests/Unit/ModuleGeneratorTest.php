<?php

declare(strict_types=1);

namespace Lattice\DevTools\Tests\Unit;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\Generator\ModuleGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleGeneratorTest extends TestCase
{
    private ModuleGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ModuleGenerator();
    }

    #[Test]
    public function it_has_name_and_description(): void
    {
        $this->assertSame('module', $this->generator->getName());
        $this->assertNotEmpty($this->generator->getDescription());
    }

    #[Test]
    public function it_generates_module_class(): void
    {
        $files = $this->generator->generate([
            'name' => 'Billing',
            'path' => 'src/Modules/Billing',
        ]);

        $this->assertNotEmpty($files);
        $this->assertContainsOnlyInstancesOf(GeneratedFile::class, $files);

        $moduleFile = $this->findFileByPath($files, 'src/Modules/Billing/BillingModule.php');
        $this->assertNotNull($moduleFile, 'Module class file should be generated');
        $this->assertSame('created', $moduleFile->type);
        $this->assertStringContainsString('declare(strict_types=1)', $moduleFile->content);
        $this->assertStringContainsString('#[Module(', $moduleFile->content);
        $this->assertStringContainsString('final class BillingModule', $moduleFile->content);
    }

    #[Test]
    public function it_generates_directory_structure(): void
    {
        $files = $this->generator->generate([
            'name' => 'Billing',
            'path' => 'src/Modules/Billing',
        ]);

        $paths = array_map(fn(GeneratedFile $f) => $f->path, $files);

        $this->assertContains('src/Modules/Billing/Domain/.gitkeep', $paths);
        $this->assertContains('src/Modules/Billing/Application/.gitkeep', $paths);
        $this->assertContains('src/Modules/Billing/Infrastructure/.gitkeep', $paths);
        $this->assertContains('src/Modules/Billing/Interfaces/.gitkeep', $paths);
    }

    /**
     * @param GeneratedFile[] $files
     */
    private function findFileByPath(array $files, string $path): ?GeneratedFile
    {
        foreach ($files as $file) {
            if ($file->path === $path) {
                return $file;
            }
        }
        return null;
    }
}
