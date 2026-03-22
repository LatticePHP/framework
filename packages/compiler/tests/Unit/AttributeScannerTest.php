<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Unit;

use Lattice\Compiler\Attributes\Controller;
use Lattice\Compiler\Attributes\Injectable;
use Lattice\Compiler\Attributes\Module;
use Lattice\Compiler\Attributes\GlobalModule;
use Lattice\Compiler\Discovery\AttributeMetadata;
use Lattice\Compiler\Discovery\AttributeScanner;
use Lattice\Compiler\Tests\Fixtures\AppModule;
use Lattice\Compiler\Tests\Fixtures\DefaultController;
use Lattice\Compiler\Tests\Fixtures\GlobalConfigModule;
use Lattice\Compiler\Tests\Fixtures\PlainClass;
use Lattice\Compiler\Tests\Fixtures\SimpleService;
use Lattice\Compiler\Tests\Fixtures\UserController;
use Lattice\Compiler\Tests\Fixtures\UserModule;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AttributeScannerTest extends TestCase
{
    private AttributeScanner $scanner;

    protected function setUp(): void
    {
        $this->scanner = new AttributeScanner();
    }

    #[Test]
    public function it_discovers_module_attribute(): void
    {
        $metadata = $this->scanner->scanClass(AppModule::class);

        self::assertNotNull($metadata);
        self::assertSame(AppModule::class, $metadata->className);
        self::assertTrue($metadata->isModule);
        self::assertContains(UserModule::class, $metadata->imports);
        self::assertContains(SimpleService::class, $metadata->providers);
        self::assertContains(DefaultController::class, $metadata->controllers);
        self::assertContains(SimpleService::class, $metadata->exports);
        self::assertFalse($metadata->isGlobal);
    }

    #[Test]
    public function it_discovers_controller_attribute(): void
    {
        $metadata = $this->scanner->scanClass(UserController::class);

        self::assertNotNull($metadata);
        self::assertSame(UserController::class, $metadata->className);
        self::assertTrue($metadata->isController);
        self::assertSame('/users', $metadata->controllerPrefix);
    }

    #[Test]
    public function it_discovers_controller_with_default_prefix(): void
    {
        $metadata = $this->scanner->scanClass(DefaultController::class);

        self::assertNotNull($metadata);
        self::assertTrue($metadata->isController);
        self::assertSame('', $metadata->controllerPrefix);
    }

    #[Test]
    public function it_discovers_injectable_attribute(): void
    {
        $metadata = $this->scanner->scanClass(SimpleService::class);

        self::assertNotNull($metadata);
        self::assertSame(SimpleService::class, $metadata->className);
        self::assertTrue($metadata->isInjectable);
    }

    #[Test]
    public function it_discovers_global_module_attribute(): void
    {
        $metadata = $this->scanner->scanClass(GlobalConfigModule::class);

        self::assertNotNull($metadata);
        self::assertTrue($metadata->isModule);
        self::assertTrue($metadata->isGlobal);
    }

    #[Test]
    public function it_discovers_global_flag_on_module(): void
    {
        $metadata = $this->scanner->scanClass(\Lattice\Compiler\Tests\Fixtures\GlobalFlagModule::class);

        self::assertNotNull($metadata);
        self::assertTrue($metadata->isModule);
        self::assertTrue($metadata->isGlobal);
    }

    #[Test]
    public function it_returns_null_for_plain_class(): void
    {
        $metadata = $this->scanner->scanClass(PlainClass::class);

        self::assertNull($metadata);
    }

    #[Test]
    public function it_scans_directory_for_attributed_classes(): void
    {
        $fixturesDir = __DIR__ . '/../Fixtures';
        $results = $this->scanner->scanDirectory($fixturesDir);

        // Should find multiple attributed classes, but not PlainClass
        self::assertNotEmpty($results);

        $classNames = array_map(fn(AttributeMetadata $m) => $m->className, $results);

        // Modules should be discovered
        self::assertContains(AppModule::class, $classNames);
        self::assertContains(UserModule::class, $classNames);

        // Controllers should be discovered
        self::assertContains(UserController::class, $classNames);

        // Injectables should be discovered
        self::assertContains(SimpleService::class, $classNames);

        // PlainClass should NOT be discovered
        self::assertNotContains(PlainClass::class, $classNames);
    }
}
