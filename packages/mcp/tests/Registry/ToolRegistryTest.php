<?php

declare(strict_types=1);

namespace Lattice\Mcp\Tests\Registry;

use Lattice\Mcp\Registry\ToolDefinition;
use Lattice\Mcp\Registry\ToolRegistry;
use Lattice\Mcp\Tests\Fixtures\ContactService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToolRegistryTest extends TestCase
{
    #[Test]
    public function test_discover_tools_from_class(): void
    {
        $registry = new ToolRegistry();
        $registry->discover(ContactService::class);

        $this->assertTrue($registry->has('create_contact'));
        $this->assertTrue($registry->has('search_contacts'));
        $this->assertTrue($registry->has('deleteContact'));
        $this->assertSame(3, $registry->count());
    }

    #[Test]
    public function test_tool_name_defaults_to_method_name(): void
    {
        $registry = new ToolRegistry();
        $registry->discover(ContactService::class);

        // deleteContact has no explicit name, so defaults to method name
        $tool = $registry->get('deleteContact');

        $this->assertNotNull($tool);
        $this->assertSame('deleteContact', $tool->name);
        $this->assertSame('Delete a contact by ID', $tool->description);
    }

    #[Test]
    public function test_tool_custom_name(): void
    {
        $registry = new ToolRegistry();
        $registry->discover(ContactService::class);

        $tool = $registry->get('create_contact');

        $this->assertNotNull($tool);
        $this->assertSame('create_contact', $tool->name);
        $this->assertSame('Creates a new CRM contact', $tool->description);
    }

    #[Test]
    public function test_tool_has_input_schema(): void
    {
        $registry = new ToolRegistry();
        $registry->discover(ContactService::class);

        $tool = $registry->get('create_contact');

        $this->assertNotNull($tool);
        $this->assertSame('object', $tool->inputSchema['type']);
        $this->assertArrayHasKey('firstName', $tool->inputSchema['properties']);
        $this->assertArrayHasKey('lastName', $tool->inputSchema['properties']);
        $this->assertArrayHasKey('email', $tool->inputSchema['properties']);
        $this->assertContains('firstName', $tool->inputSchema['required']);
        $this->assertContains('lastName', $tool->inputSchema['required']);
        $this->assertContains('email', $tool->inputSchema['required']);
    }

    #[Test]
    public function test_tool_to_array(): void
    {
        $registry = new ToolRegistry();
        $registry->discover(ContactService::class);

        $tool = $registry->get('create_contact');
        $arr = $tool->toArray();

        $this->assertSame('create_contact', $arr['name']);
        $this->assertSame('Creates a new CRM contact', $arr['description']);
        $this->assertArrayHasKey('inputSchema', $arr);
    }

    #[Test]
    public function test_to_list(): void
    {
        $registry = new ToolRegistry();
        $registry->discover(ContactService::class);

        $list = $registry->toList();

        $this->assertCount(3, $list);
        $names = array_column($list, 'name');
        $this->assertContains('create_contact', $names);
    }

    #[Test]
    public function test_register_direct(): void
    {
        $registry = new ToolRegistry();

        $definition = new ToolDefinition(
            name: 'my_tool',
            description: 'A custom tool',
            inputSchema: ['type' => 'object', 'properties' => new \stdClass()],
            className: 'FakeClass',
            methodName: 'fakeMethod',
        );

        $registry->register($definition);

        $this->assertTrue($registry->has('my_tool'));
        $this->assertSame($definition, $registry->get('my_tool'));
    }

    #[Test]
    public function test_get_unknown_returns_null(): void
    {
        $registry = new ToolRegistry();

        $this->assertNull($registry->get('nonexistent'));
        $this->assertFalse($registry->has('nonexistent'));
    }

    #[Test]
    public function test_non_attributed_methods_not_discovered(): void
    {
        $registry = new ToolRegistry();
        $registry->discover(ContactService::class);

        // internalMethod() has no #[Tool] — should not be discovered
        $this->assertFalse($registry->has('internalMethod'));
    }

    #[Test]
    public function test_tool_with_optional_params_schema(): void
    {
        $registry = new ToolRegistry();
        $registry->discover(ContactService::class);

        $tool = $registry->get('search_contacts');

        $this->assertNotNull($tool);
        // 'query' is required, 'limit' has default
        $this->assertContains('query', $tool->inputSchema['required']);
        $this->assertNotContains('limit', $tool->inputSchema['required'] ?? []);
        $this->assertSame(10, $tool->inputSchema['properties']['limit']['default']);
    }
}
