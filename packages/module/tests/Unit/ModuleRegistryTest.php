<?php

declare(strict_types=1);

namespace Lattice\Module\Tests\Unit;

use Lattice\Contracts\Module\ModuleDefinitionInterface;
use Lattice\Module\Exception\ModuleNotFoundException;
use Lattice\Module\Exception\CircularDependencyException;
use Lattice\Module\ModuleDefinition;
use Lattice\Module\ModuleRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ModuleRegistry::class)]
final class ModuleRegistryTest extends TestCase
{
    private ModuleRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ModuleRegistry();
    }

    #[Test]
    public function it_registers_a_module(): void
    {
        $definition = new ModuleDefinition();
        $this->registry->register('App\\CoreModule', $definition);

        self::assertTrue($this->registry->has('App\\CoreModule'));
    }

    #[Test]
    public function it_retrieves_a_registered_module(): void
    {
        $definition = new ModuleDefinition(providers: ['App\\UserService']);
        $this->registry->register('App\\UserModule', $definition);

        $retrieved = $this->registry->get('App\\UserModule');

        self::assertSame($definition, $retrieved);
    }

    #[Test]
    public function it_throws_for_unregistered_module(): void
    {
        $this->expectException(ModuleNotFoundException::class);

        $this->registry->get('App\\NonExistent');
    }

    #[Test]
    public function it_returns_false_for_unregistered_module(): void
    {
        self::assertFalse($this->registry->has('App\\NonExistent'));
    }

    #[Test]
    public function it_returns_all_registered_modules(): void
    {
        $def1 = new ModuleDefinition();
        $def2 = new ModuleDefinition();

        $this->registry->register('App\\Module1', $def1);
        $this->registry->register('App\\Module2', $def2);

        $all = $this->registry->all();

        self::assertCount(2, $all);
        self::assertSame($def1, $all['App\\Module1']);
        self::assertSame($def2, $all['App\\Module2']);
    }

    #[Test]
    public function it_returns_topological_boot_order(): void
    {
        // Module C depends on nothing
        $defC = new ModuleDefinition(imports: []);
        // Module B depends on C
        $defB = new ModuleDefinition(imports: ['App\\ModuleC']);
        // Module A depends on B
        $defA = new ModuleDefinition(imports: ['App\\ModuleB']);

        $this->registry->register('App\\ModuleA', $defA);
        $this->registry->register('App\\ModuleB', $defB);
        $this->registry->register('App\\ModuleC', $defC);

        $bootOrder = $this->registry->getBootOrder();

        // C should come before B, and B before A
        $posC = array_search('App\\ModuleC', $bootOrder, true);
        $posB = array_search('App\\ModuleB', $bootOrder, true);
        $posA = array_search('App\\ModuleA', $bootOrder, true);

        self::assertLessThan($posB, $posC);
        self::assertLessThan($posA, $posB);
    }

    #[Test]
    public function it_handles_diamond_dependency(): void
    {
        // D depends on nothing
        $defD = new ModuleDefinition(imports: []);
        // B and C both depend on D
        $defB = new ModuleDefinition(imports: ['App\\ModuleD']);
        $defC = new ModuleDefinition(imports: ['App\\ModuleD']);
        // A depends on B and C
        $defA = new ModuleDefinition(imports: ['App\\ModuleB', 'App\\ModuleC']);

        $this->registry->register('App\\ModuleA', $defA);
        $this->registry->register('App\\ModuleB', $defB);
        $this->registry->register('App\\ModuleC', $defC);
        $this->registry->register('App\\ModuleD', $defD);

        $bootOrder = $this->registry->getBootOrder();

        self::assertCount(4, $bootOrder);

        $posD = array_search('App\\ModuleD', $bootOrder, true);
        $posB = array_search('App\\ModuleB', $bootOrder, true);
        $posC = array_search('App\\ModuleC', $bootOrder, true);
        $posA = array_search('App\\ModuleA', $bootOrder, true);

        self::assertLessThan($posB, $posD);
        self::assertLessThan($posC, $posD);
        self::assertLessThan($posA, $posB);
        self::assertLessThan($posA, $posC);
    }

    #[Test]
    public function it_detects_circular_dependency(): void
    {
        $defA = new ModuleDefinition(imports: ['App\\ModuleB']);
        $defB = new ModuleDefinition(imports: ['App\\ModuleA']);

        $this->registry->register('App\\ModuleA', $defA);
        $this->registry->register('App\\ModuleB', $defB);

        $this->expectException(CircularDependencyException::class);

        $this->registry->getBootOrder();
    }

    #[Test]
    public function it_returns_modules_with_no_imports_first(): void
    {
        $defA = new ModuleDefinition(imports: []);
        $defB = new ModuleDefinition(imports: ['App\\ModuleA']);

        $this->registry->register('App\\ModuleB', $defB);
        $this->registry->register('App\\ModuleA', $defA);

        $bootOrder = $this->registry->getBootOrder();

        self::assertSame('App\\ModuleA', $bootOrder[0]);
        self::assertSame('App\\ModuleB', $bootOrder[1]);
    }
}
