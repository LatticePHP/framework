<?php

declare(strict_types=1);

namespace Lattice\Mcp\Tests\Schema;

use Lattice\Mcp\Schema\ToolSchemaGenerator;
use Lattice\Mcp\Tests\Fixtures\SchemaTestService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ToolSchemaGeneratorTest extends TestCase
{
    private ToolSchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ToolSchemaGenerator();
    }

    #[Test]
    public function test_maps_string_type(): void
    {
        $schema = $this->generateFor('allTypes');

        $this->assertSame('string', $schema['properties']['name']['type']);
    }

    #[Test]
    public function test_maps_int_type(): void
    {
        $schema = $this->generateFor('allTypes');

        $this->assertSame('integer', $schema['properties']['age']['type']);
    }

    #[Test]
    public function test_maps_float_type(): void
    {
        $schema = $this->generateFor('allTypes');

        $this->assertSame('number', $schema['properties']['score']['type']);
    }

    #[Test]
    public function test_maps_bool_type(): void
    {
        $schema = $this->generateFor('allTypes');

        $this->assertSame('boolean', $schema['properties']['active']['type']);
    }

    #[Test]
    public function test_maps_array_type(): void
    {
        $schema = $this->generateFor('allTypes');

        $this->assertSame('array', $schema['properties']['tags']['type']);
    }

    #[Test]
    public function test_all_params_required_when_no_defaults(): void
    {
        $schema = $this->generateFor('allTypes');

        $this->assertCount(5, $schema['required']);
        $this->assertContains('name', $schema['required']);
        $this->assertContains('age', $schema['required']);
        $this->assertContains('score', $schema['required']);
        $this->assertContains('active', $schema['required']);
        $this->assertContains('tags', $schema['required']);
    }

    #[Test]
    public function test_defaults_are_not_required(): void
    {
        $schema = $this->generateFor('withDefaults');

        $this->assertContains('name', $schema['required']);
        $this->assertNotContains('limit', $schema['required']);
        $this->assertNotContains('verbose', $schema['required']);
    }

    #[Test]
    public function test_default_values_included(): void
    {
        $schema = $this->generateFor('withDefaults');

        $this->assertSame(10, $schema['properties']['limit']['default']);
        $this->assertSame(false, $schema['properties']['verbose']['default']);
    }

    #[Test]
    public function test_nullable_param_not_required(): void
    {
        $schema = $this->generateFor('withNullable');

        $this->assertContains('name', $schema['required'] ?? []);
        // nickname is nullable with default null — not required
        $this->assertNotContains('nickname', $schema['required'] ?? []);
    }

    #[Test]
    public function test_maps_backed_enum(): void
    {
        $schema = $this->generateFor('withEnum');

        $this->assertSame('string', $schema['properties']['priority']['type']);
        $this->assertContains('low', $schema['properties']['priority']['enum']);
        $this->assertContains('medium', $schema['properties']['priority']['enum']);
        $this->assertContains('high', $schema['properties']['priority']['enum']);
    }

    #[Test]
    public function test_docblock_descriptions(): void
    {
        $schema = $this->generateFor('withDocblock');

        $this->assertSame('The first name of the person', $schema['properties']['firstName']['description']);
        $this->assertSame('The last name of the person', $schema['properties']['lastName']['description']);
    }

    #[Test]
    public function test_no_params_produces_empty_object_schema(): void
    {
        $schema = $this->generateFor('noParams');

        $this->assertSame('object', $schema['type']);
        $this->assertInstanceOf(\stdClass::class, $schema['properties']);
        $this->assertArrayNotHasKey('required', $schema);
    }

    #[Test]
    public function test_schema_is_json_serializable(): void
    {
        $schema = $this->generateFor('allTypes');

        $json = json_encode($schema);
        $this->assertNotFalse($json);

        $decoded = json_decode((string) $json, true);
        $this->assertSame('object', $decoded['type']);
    }

    /**
     * @return array<string, mixed>
     */
    private function generateFor(string $method): array
    {
        $reflection = new \ReflectionMethod(SchemaTestService::class, $method);

        return $this->generator->generate($reflection);
    }
}
