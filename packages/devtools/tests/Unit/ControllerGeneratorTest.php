<?php

declare(strict_types=1);

namespace Lattice\DevTools\Tests\Unit;

use Lattice\DevTools\GeneratedFile;
use Lattice\DevTools\Generator\ControllerGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ControllerGeneratorTest extends TestCase
{
    private ControllerGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ControllerGenerator();
    }

    #[Test]
    public function it_has_name_and_description(): void
    {
        $this->assertSame('controller', $this->generator->getName());
        $this->assertNotEmpty($this->generator->getDescription());
    }

    #[Test]
    public function it_generates_controller_with_route_attributes(): void
    {
        $files = $this->generator->generate([
            'name' => 'User',
            'module' => 'App',
            'methods' => ['index', 'show', 'create'],
        ]);

        $this->assertNotEmpty($files);

        $controllerFile = $this->findFileByPath($files, 'app/Http/UserController.php');
        $this->assertNotNull($controllerFile);
        $this->assertSame('created', $controllerFile->type);
        $this->assertStringContainsString('declare(strict_types=1)', $controllerFile->content);
        $this->assertStringContainsString('#[Controller', $controllerFile->content);
        $this->assertStringContainsString('#[Get(', $controllerFile->content);
        $this->assertStringContainsString('#[Post(', $controllerFile->content);
        $this->assertStringContainsString('final class UserController', $controllerFile->content);
    }

    #[Test]
    public function it_generates_test_file(): void
    {
        $files = $this->generator->generate([
            'name' => 'User',
            'module' => 'App',
            'methods' => ['index'],
        ]);

        $testFile = $this->findFileByPath($files, 'tests/Http/UserControllerTest.php');
        $this->assertNotNull($testFile, 'Test file should be generated');
        $this->assertStringContainsString('UserControllerTest', $testFile->content);
    }

    #[Test]
    public function it_generates_crud_methods(): void
    {
        $files = $this->generator->generate([
            'name' => 'Product',
            'module' => 'App',
            'methods' => ['index', 'show', 'create', 'update', 'delete'],
        ]);

        $controllerFile = $this->findFileByPath($files, 'app/Http/ProductController.php');
        $this->assertNotNull($controllerFile);
        $this->assertStringContainsString('public function index()', $controllerFile->content);
        $this->assertStringContainsString('public function show(', $controllerFile->content);
        $this->assertStringContainsString('public function create(', $controllerFile->content);
        $this->assertStringContainsString('public function update(', $controllerFile->content);
        $this->assertStringContainsString('public function delete(', $controllerFile->content);
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
