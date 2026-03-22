<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Tests\Mcp\Tools;

use Lattice\Catalyst\Mcp\Tools\DatabaseSchemaTool;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DatabaseSchemaToolTest extends TestCase
{
    #[Test]
    public function test_returns_correct_tool_metadata(): void
    {
        $tool = new DatabaseSchemaTool();

        $this->assertSame('db_schema', $tool->getName());
        $this->assertNotEmpty($tool->getDescription());
        $this->assertIsArray($tool->getInputSchema());
    }

    #[Test]
    public function test_list_tables(): void
    {
        $tool = new DatabaseSchemaTool();
        $tool->setSchema([
            'users' => [
                'columns' => [
                    'id' => ['type' => 'bigint', 'nullable' => false, 'default' => null],
                    'name' => ['type' => 'varchar(255)', 'nullable' => false, 'default' => null],
                    'email' => ['type' => 'varchar(255)', 'nullable' => false, 'default' => null],
                ],
                'indexes' => ['PRIMARY', 'users_email_unique'],
            ],
            'posts' => [
                'columns' => [
                    'id' => ['type' => 'bigint', 'nullable' => false, 'default' => null],
                    'title' => ['type' => 'varchar(255)', 'nullable' => false, 'default' => null],
                ],
                'indexes' => ['PRIMARY'],
            ],
        ]);

        $result = $tool->execute([]);

        $this->assertSame(2, $result['total']);
        $this->assertCount(2, $result['tables']);
        $this->assertSame('users', $result['tables'][0]['name']);
        $this->assertSame(3, $result['tables'][0]['columns']);
        $this->assertSame(2, $result['tables'][0]['indexes']);
    }

    #[Test]
    public function test_describe_specific_table(): void
    {
        $tool = new DatabaseSchemaTool();
        $tool->setSchema([
            'users' => [
                'columns' => [
                    'id' => ['type' => 'bigint', 'nullable' => false, 'default' => null],
                    'name' => ['type' => 'varchar(255)', 'nullable' => false, 'default' => null],
                ],
                'indexes' => ['PRIMARY'],
            ],
        ]);

        $result = $tool->execute(['table' => 'users']);

        $this->assertSame('users', $result['table']);
        $this->assertArrayHasKey('id', $result['columns']);
        $this->assertSame('bigint', $result['columns']['id']['type']);
    }

    #[Test]
    public function test_describe_missing_table(): void
    {
        $tool = new DatabaseSchemaTool();

        $result = $tool->execute(['table' => 'nonexistent']);

        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('not found', $result['error']);
    }

    #[Test]
    public function test_list_empty_schema(): void
    {
        $tool = new DatabaseSchemaTool();

        $result = $tool->execute([]);

        $this->assertSame(0, $result['total']);
        $this->assertSame([], $result['tables']);
    }
}
