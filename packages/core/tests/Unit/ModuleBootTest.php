<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit;

use Lattice\Core\Application;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleBootTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lattice-module-boot-test-' . uniqid();
        mkdir($this->basePath, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            @rmdir($this->basePath);
        }
    }

    #[Test]
    public function test_boot_registers_module_providers_in_container(): void
    {
        // Skip if module package not available
        if (!class_exists(\Lattice\Module\Attribute\Module::class)) {
            $this->markTestSkipped('lattice/module package not available');
        }

        $app = new Application(
            basePath: $this->basePath,
            modules: [Fixtures\TestModule::class],
        );

        $app->boot();

        $container = $app->getContainer();

        // The provider class should be registered in the container
        $this->assertTrue($container->has(Fixtures\TestService::class));
    }

    #[Test]
    public function test_boot_collects_controllers_from_modules(): void
    {
        if (!class_exists(\Lattice\Module\Attribute\Module::class)) {
            $this->markTestSkipped('lattice/module package not available');
        }

        $app = new Application(
            basePath: $this->basePath,
            modules: [Fixtures\TestModule::class],
        );

        $app->boot();

        $controllers = $app->getControllers();

        $this->assertContains(Fixtures\TestController::class, $controllers);
    }

    #[Test]
    public function test_boot_registers_module_exports_as_accessible(): void
    {
        if (!class_exists(\Lattice\Module\Attribute\Module::class)) {
            $this->markTestSkipped('lattice/module package not available');
        }

        $app = new Application(
            basePath: $this->basePath,
            modules: [Fixtures\TestModule::class],
        );

        $app->boot();

        // Module exports should be tracked in definitions
        $definitions = $app->getModuleDefinitions();

        $this->assertArrayHasKey(Fixtures\TestModule::class, $definitions);

        $exports = $definitions[Fixtures\TestModule::class]->getExports();

        $this->assertContains(Fixtures\TestService::class, $exports);
    }

    #[Test]
    public function test_boot_with_no_modules_works(): void
    {
        $app = new Application(
            basePath: $this->basePath,
            modules: [],
        );

        // Should not throw
        $app->boot();

        $this->assertSame([], $app->getControllers());
        $this->assertSame([], $app->getModuleDefinitions());
    }

    #[Test]
    public function test_boot_preserves_container_and_config(): void
    {
        $app = new Application(
            basePath: $this->basePath,
            modules: [],
        );

        $app->boot();

        // Core bindings should still work
        $this->assertSame($app, $app->getContainer()->make(Application::class));
        $this->assertNotNull($app->getConfig());
    }
}
