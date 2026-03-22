<?php

declare(strict_types=1);

namespace Lattice\Compiler\Tests\Unit;

use InvalidArgumentException;
use Lattice\Compiler\Graph\ModuleGraph;
use Lattice\Compiler\Graph\ModuleNode;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ModuleGraphTest extends TestCase
{
    #[Test]
    public function test_get_modules_returns_all_modules(): void
    {
        $nodeA = new ModuleNode(className: 'App\\ModuleA', providers: ['App\\ServiceA']);
        $nodeB = new ModuleNode(className: 'App\\ModuleB', providers: ['App\\ServiceB']);

        $graph = new ModuleGraph(
            modules: ['App\\ModuleA' => $nodeA, 'App\\ModuleB' => $nodeB],
            topologicalOrder: ['App\\ModuleA', 'App\\ModuleB'],
        );

        $modules = $graph->getModules();

        self::assertCount(2, $modules);
        self::assertSame($nodeA, $modules['App\\ModuleA']);
        self::assertSame($nodeB, $modules['App\\ModuleB']);
    }

    #[Test]
    public function test_get_module_returns_specific_module(): void
    {
        $node = new ModuleNode(className: 'App\\ModuleA');
        $graph = new ModuleGraph(
            modules: ['App\\ModuleA' => $node],
            topologicalOrder: ['App\\ModuleA'],
        );

        self::assertSame($node, $graph->getModule('App\\ModuleA'));
    }

    #[Test]
    public function test_get_module_throws_for_unknown_module(): void
    {
        $graph = new ModuleGraph(modules: [], topologicalOrder: []);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Module 'App\\Unknown' not found in the graph.");

        $graph->getModule('App\\Unknown');
    }

    #[Test]
    public function test_get_topological_order(): void
    {
        $nodeA = new ModuleNode(className: 'App\\ModuleA');
        $nodeB = new ModuleNode(className: 'App\\ModuleB', imports: ['App\\ModuleA']);

        $graph = new ModuleGraph(
            modules: ['App\\ModuleA' => $nodeA, 'App\\ModuleB' => $nodeB],
            topologicalOrder: ['App\\ModuleA', 'App\\ModuleB'],
        );

        self::assertSame(['App\\ModuleA', 'App\\ModuleB'], $graph->getTopologicalOrder());
    }

    #[Test]
    public function test_get_exports_for_module(): void
    {
        $node = new ModuleNode(
            className: 'App\\ModuleA',
            exports: ['App\\ServiceA', 'App\\ServiceB'],
        );

        $graph = new ModuleGraph(
            modules: ['App\\ModuleA' => $node],
            topologicalOrder: ['App\\ModuleA'],
        );

        self::assertSame(['App\\ServiceA', 'App\\ServiceB'], $graph->getExportsFor('App\\ModuleA'));
    }

    #[Test]
    public function test_is_global_returns_true_for_global_module(): void
    {
        $node = new ModuleNode(className: 'App\\GlobalModule', isGlobal: true);

        $graph = new ModuleGraph(
            modules: ['App\\GlobalModule' => $node],
            topologicalOrder: ['App\\GlobalModule'],
        );

        self::assertTrue($graph->isGlobal('App\\GlobalModule'));
    }

    #[Test]
    public function test_is_global_returns_false_for_non_global_module(): void
    {
        $node = new ModuleNode(className: 'App\\LocalModule', isGlobal: false);

        $graph = new ModuleGraph(
            modules: ['App\\LocalModule' => $node],
            topologicalOrder: ['App\\LocalModule'],
        );

        self::assertFalse($graph->isGlobal('App\\LocalModule'));
    }

    #[Test]
    public function test_module_node_to_array(): void
    {
        $node = new ModuleNode(
            className: 'App\\UserModule',
            imports: ['App\\AuthModule'],
            providers: ['App\\UserService'],
            controllers: ['App\\UserController'],
            exports: ['App\\UserService'],
            isGlobal: true,
        );

        $array = $node->toArray();

        self::assertSame('App\\UserModule', $array['className']);
        self::assertSame(['App\\AuthModule'], $array['imports']);
        self::assertSame(['App\\UserService'], $array['providers']);
        self::assertSame(['App\\UserController'], $array['controllers']);
        self::assertSame(['App\\UserService'], $array['exports']);
        self::assertTrue($array['isGlobal']);
    }

    #[Test]
    public function test_module_node_from_array(): void
    {
        $data = [
            'className' => 'App\\UserModule',
            'imports' => ['App\\AuthModule'],
            'providers' => ['App\\UserService'],
            'controllers' => ['App\\UserController'],
            'exports' => ['App\\UserService'],
            'isGlobal' => true,
        ];

        $node = ModuleNode::fromArray($data);

        self::assertSame('App\\UserModule', $node->className);
        self::assertSame(['App\\AuthModule'], $node->imports);
        self::assertSame(['App\\UserService'], $node->providers);
        self::assertSame(['App\\UserController'], $node->controllers);
        self::assertSame(['App\\UserService'], $node->exports);
        self::assertTrue($node->isGlobal);
    }

    #[Test]
    public function test_module_node_from_array_with_missing_optional_fields(): void
    {
        $data = [
            'className' => 'App\\MinimalModule',
        ];

        $node = ModuleNode::fromArray($data);

        self::assertSame('App\\MinimalModule', $node->className);
        self::assertSame([], $node->imports);
        self::assertSame([], $node->providers);
        self::assertSame([], $node->controllers);
        self::assertSame([], $node->exports);
        self::assertFalse($node->isGlobal);
    }

    #[Test]
    public function test_module_node_roundtrip_to_array_from_array(): void
    {
        $original = new ModuleNode(
            className: 'App\\RoundtripModule',
            imports: ['App\\DepA', 'App\\DepB'],
            providers: ['App\\SvcA'],
            controllers: ['App\\CtrlA'],
            exports: ['App\\SvcA'],
            isGlobal: false,
        );

        $restored = ModuleNode::fromArray($original->toArray());

        self::assertSame($original->className, $restored->className);
        self::assertSame($original->imports, $restored->imports);
        self::assertSame($original->providers, $restored->providers);
        self::assertSame($original->controllers, $restored->controllers);
        self::assertSame($original->exports, $restored->exports);
        self::assertSame($original->isGlobal, $restored->isGlobal);
    }
}
