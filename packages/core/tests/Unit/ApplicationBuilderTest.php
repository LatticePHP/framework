<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Unit;

use Lattice\Core\Application;
use Lattice\Core\ApplicationBuilder;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApplicationBuilderTest extends TestCase
{
    private string $basePath;

    protected function setUp(): void
    {
        $this->basePath = sys_get_temp_dir() . '/lattice-test-' . uniqid();
        mkdir($this->basePath, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->basePath)) {
            rmdir($this->basePath);
        }
    }

    #[Test]
    public function configure_returns_application_builder(): void
    {
        $builder = Application::configure(basePath: $this->basePath);

        $this->assertInstanceOf(ApplicationBuilder::class, $builder);
    }

    #[Test]
    public function with_modules_is_fluent(): void
    {
        $builder = Application::configure(basePath: $this->basePath);

        $result = $builder->withModules([]);

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function with_http_is_fluent(): void
    {
        $builder = Application::configure(basePath: $this->basePath);

        $result = $builder->withHttp();

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function with_observability_is_fluent(): void
    {
        $builder = Application::configure(basePath: $this->basePath);

        $result = $builder->withObservability();

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function create_returns_application(): void
    {
        $app = Application::configure(basePath: $this->basePath)
            ->withModules([])
            ->withHttp()
            ->withObservability()
            ->create();

        $this->assertInstanceOf(Application::class, $app);
    }

    #[Test]
    public function modules_are_registered_on_application(): void
    {
        $app = Application::configure(basePath: $this->basePath)
            ->withModules([StubModule::class])
            ->create();

        $this->assertContains(StubModule::class, $app->getModules());
    }

    #[Test]
    public function base_path_is_set_on_application(): void
    {
        $app = Application::configure(basePath: $this->basePath)
            ->create();

        $this->assertSame($this->basePath, $app->getBasePath());
    }

    #[Test]
    public function with_grpc_is_fluent(): void
    {
        $builder = Application::configure(basePath: $this->basePath);

        $result = $builder->withGrpc();

        $this->assertSame($builder, $result);
    }

    #[Test]
    public function transport_flags_are_stored(): void
    {
        $app = Application::configure(basePath: $this->basePath)
            ->withHttp()
            ->withGrpc()
            ->create();

        $this->assertTrue($app->hasTransport('http'));
        $this->assertTrue($app->hasTransport('grpc'));
        $this->assertFalse($app->hasTransport('message'));
    }
}

// Stub for testing
class StubModule {}
