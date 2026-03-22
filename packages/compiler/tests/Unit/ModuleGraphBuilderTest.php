<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Unit;

use Lattice\Compiler\Discovery\ModuleMetadata;
use Lattice\Compiler\Exceptions\CircularDependencyException;
use Lattice\Compiler\Exceptions\ExportViolationException;
use Lattice\Compiler\Exceptions\UnresolvedImportException;
use Lattice\Compiler\Graph\ModuleGraph;
use Lattice\Compiler\Graph\ModuleGraphBuilder;
use Lattice\Compiler\Graph\ModuleNode;
use Lattice\Compiler\Tests\Fixtures\AppModule;
use Lattice\Compiler\Tests\Fixtures\ConfigService;
use Lattice\Compiler\Tests\Fixtures\DefaultController;
use Lattice\Compiler\Tests\Fixtures\GlobalConfigModule;
use Lattice\Compiler\Tests\Fixtures\SimpleService;
use Lattice\Compiler\Tests\Fixtures\UserController;
use Lattice\Compiler\Tests\Fixtures\UserModule;
use Lattice\Compiler\Tests\Fixtures\UserService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleGraphBuilderTest extends TestCase
{
    private ModuleGraphBuilder $builder;

    protected function setUp(): void
    {
        $this->builder = new ModuleGraphBuilder();
    }

    #[Test]
    public function it_builds_a_simple_graph(): void
    {
        $this->builder->addModule(UserModule::class, new ModuleMetadata(
            imports: [],
            providers: [UserService::class],
            controllers: [UserController::class],
            exports: [UserService::class],
            isGlobal: false,
        ));

        $this->builder->addModule(AppModule::class, new ModuleMetadata(
            imports: [UserModule::class],
            providers: [SimpleService::class],
            controllers: [DefaultController::class],
            exports: [SimpleService::class],
            isGlobal: false,
        ));

        $graph = $this->builder->build();

        self::assertInstanceOf(ModuleGraph::class, $graph);
        self::assertCount(2, $graph->getModules());
    }

    #[Test]
    public function it_resolves_topological_order(): void
    {
        $this->builder->addModule(UserModule::class, new ModuleMetadata(
            imports: [],
            providers: [UserService::class],
            controllers: [UserController::class],
            exports: [UserService::class],
            isGlobal: false,
        ));

        $this->builder->addModule(AppModule::class, new ModuleMetadata(
            imports: [UserModule::class],
            providers: [SimpleService::class],
            controllers: [DefaultController::class],
            exports: [SimpleService::class],
            isGlobal: false,
        ));

        $graph = $this->builder->build();
        $order = $graph->getTopologicalOrder();

        // UserModule must come before AppModule since AppModule imports it
        $userIndex = array_search(UserModule::class, $order, true);
        $appIndex = array_search(AppModule::class, $order, true);

        self::assertNotFalse($userIndex);
        self::assertNotFalse($appIndex);
        self::assertLessThan($appIndex, $userIndex);
    }

    #[Test]
    public function it_detects_circular_dependencies(): void
    {
        $this->builder->addModule('CircularA', new ModuleMetadata(
            imports: ['CircularB'],
            providers: [],
            controllers: [],
            exports: [],
            isGlobal: false,
        ));

        $this->builder->addModule('CircularB', new ModuleMetadata(
            imports: ['CircularA'],
            providers: [],
            controllers: [],
            exports: [],
            isGlobal: false,
        ));

        $this->expectException(CircularDependencyException::class);
        $this->expectExceptionMessageMatches('/circular/i');

        $this->builder->build();
    }

    #[Test]
    public function it_detects_unresolved_imports(): void
    {
        $this->builder->addModule('SomeModule', new ModuleMetadata(
            imports: ['NonExistent\\Module'],
            providers: [],
            controllers: [],
            exports: [],
            isGlobal: false,
        ));

        $this->expectException(UnresolvedImportException::class);
        $this->expectExceptionMessageMatches('/NonExistent\\\\Module/');

        $this->builder->build();
    }

    #[Test]
    public function it_enforces_export_visibility(): void
    {
        $this->builder->addModule('ViolatingModule', new ModuleMetadata(
            imports: [],
            providers: [SimpleService::class],
            exports: [UserService::class], // UserService not in providers
            isGlobal: false,
        ));

        $this->expectException(ExportViolationException::class);
        $this->expectExceptionMessageMatches('/UserService/');

        $this->builder->build();
    }

    #[Test]
    public function it_retrieves_module_node(): void
    {
        $this->builder->addModule(UserModule::class, new ModuleMetadata(
            imports: [],
            providers: [UserService::class],
            controllers: [UserController::class],
            exports: [UserService::class],
            isGlobal: false,
        ));

        $graph = $this->builder->build();
        $node = $graph->getModule(UserModule::class);

        self::assertInstanceOf(ModuleNode::class, $node);
        self::assertSame(UserModule::class, $node->className);
        self::assertContains(UserService::class, $node->providers);
        self::assertContains(UserController::class, $node->controllers);
        self::assertContains(UserService::class, $node->exports);
        self::assertFalse($node->isGlobal);
    }

    #[Test]
    public function it_handles_global_modules(): void
    {
        $this->builder->addModule(GlobalConfigModule::class, new ModuleMetadata(
            imports: [],
            providers: [ConfigService::class],
            controllers: [],
            exports: [ConfigService::class],
            isGlobal: true,
        ));

        $graph = $this->builder->build();

        self::assertTrue($graph->isGlobal(GlobalConfigModule::class));
    }

    #[Test]
    public function it_returns_exports_for_module(): void
    {
        $this->builder->addModule(UserModule::class, new ModuleMetadata(
            imports: [],
            providers: [UserService::class],
            controllers: [UserController::class],
            exports: [UserService::class],
            isGlobal: false,
        ));

        $graph = $this->builder->build();
        $exports = $graph->getExportsFor(UserModule::class);

        self::assertContains(UserService::class, $exports);
    }

    #[Test]
    public function it_detects_three_node_circular_dependency(): void
    {
        $this->builder->addModule('A', new ModuleMetadata(
            imports: ['B'],
            providers: [],
            controllers: [],
            exports: [],
            isGlobal: false,
        ));

        $this->builder->addModule('B', new ModuleMetadata(
            imports: ['C'],
            providers: [],
            controllers: [],
            exports: [],
            isGlobal: false,
        ));

        $this->builder->addModule('C', new ModuleMetadata(
            imports: ['A'],
            providers: [],
            controllers: [],
            exports: [],
            isGlobal: false,
        ));

        $this->expectException(CircularDependencyException::class);

        $this->builder->build();
    }
}
