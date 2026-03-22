<?php

declare(strict_types=1);

namespace Lattice\Ai\Tests\Tools;

use Lattice\Ai\Tools\AiToolAttribute;
use Lattice\Ai\Tools\ToolDefinition;
use Lattice\Ai\Tools\ToolSchemaGenerator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

// Test fixtures for schema generation
enum TestColor: string
{
    case Red = 'red';
    case Blue = 'blue';
    case Green = 'green';
}

final class ToolTestFixture
{
    #[AiToolAttribute(name: 'getWeather', description: 'Get the weather for a city')]
    public function getWeather(string $city, string $unit = 'celsius'): string
    {
        return '';
    }

    #[AiToolAttribute(name: 'calculate', description: 'Calculate a math expression')]
    public function calculate(float $a, float $b, string $operation): float
    {
        return 0.0;
    }

    /**
     * @param string $query The search query
     * @param int $limit Maximum results to return
     */
    #[AiToolAttribute(name: 'search', description: 'Search for documents')]
    public function search(string $query, int $limit = 10): array
    {
        return [];
    }

    #[AiToolAttribute(name: 'setColor', description: 'Set a color')]
    public function setColor(TestColor $color): void {}

    #[AiToolAttribute(name: 'optionalParam', description: 'Test nullable')]
    public function optionalParam(?string $name, bool $active = true): void {}

    public function noAttribute(string $foo): void {}
}

final class ToolSchemaGeneratorTest extends TestCase
{
    private ToolSchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new ToolSchemaGenerator();
    }

    #[Test]
    public function it_generates_schema_from_attributed_method(): void
    {
        $method = new ReflectionMethod(ToolTestFixture::class, 'getWeather');
        $definition = $this->generator->fromMethod($method);

        $this->assertNotNull($definition);
        $this->assertSame('getWeather', $definition->name);
        $this->assertSame('Get the weather for a city', $definition->description);
    }

    #[Test]
    public function it_returns_null_for_non_attributed_method(): void
    {
        $method = new ReflectionMethod(ToolTestFixture::class, 'noAttribute');
        $definition = $this->generator->fromMethod($method);

        $this->assertNull($definition);
    }

    #[Test]
    public function it_maps_string_parameters(): void
    {
        $method = new ReflectionMethod(ToolTestFixture::class, 'getWeather');
        $definition = $this->generator->fromMethod($method);

        $this->assertNotNull($definition);
        $props = $definition->parameters['properties'];

        $this->assertSame('string', $props['city']['type']);
        $this->assertSame('string', $props['unit']['type']);
        $this->assertSame('celsius', $props['unit']['default']);
    }

    #[Test]
    public function it_identifies_required_parameters(): void
    {
        $method = new ReflectionMethod(ToolTestFixture::class, 'getWeather');
        $definition = $this->generator->fromMethod($method);

        $this->assertNotNull($definition);
        $this->assertContains('city', $definition->parameters['required']);
        $this->assertNotContains('unit', $definition->parameters['required']);
    }

    #[Test]
    public function it_maps_float_parameters(): void
    {
        $method = new ReflectionMethod(ToolTestFixture::class, 'calculate');
        $definition = $this->generator->fromMethod($method);

        $this->assertNotNull($definition);
        $props = $definition->parameters['properties'];

        $this->assertSame('number', $props['a']['type']);
        $this->assertSame('number', $props['b']['type']);
        $this->assertSame('string', $props['operation']['type']);
    }

    #[Test]
    public function it_extracts_docblock_descriptions(): void
    {
        $method = new ReflectionMethod(ToolTestFixture::class, 'search');
        $definition = $this->generator->fromMethod($method);

        $this->assertNotNull($definition);
        $props = $definition->parameters['properties'];

        $this->assertSame('The search query', $props['query']['description']);
        $this->assertSame('Maximum results to return', $props['limit']['description']);
    }

    #[Test]
    public function it_maps_integer_parameters_with_defaults(): void
    {
        $method = new ReflectionMethod(ToolTestFixture::class, 'search');
        $definition = $this->generator->fromMethod($method);

        $this->assertNotNull($definition);
        $props = $definition->parameters['properties'];

        $this->assertSame('integer', $props['limit']['type']);
        $this->assertSame(10, $props['limit']['default']);
    }

    #[Test]
    public function it_maps_backed_enum_parameters(): void
    {
        $method = new ReflectionMethod(ToolTestFixture::class, 'setColor');
        $definition = $this->generator->fromMethod($method);

        $this->assertNotNull($definition);
        $props = $definition->parameters['properties'];

        $this->assertSame('string', $props['color']['type']);
        $this->assertSame(['red', 'blue', 'green'], $props['color']['enum']);
    }

    #[Test]
    public function it_handles_nullable_parameters(): void
    {
        $method = new ReflectionMethod(ToolTestFixture::class, 'optionalParam');
        $definition = $this->generator->fromMethod($method);

        $this->assertNotNull($definition);
        $props = $definition->parameters['properties'];

        // Nullable should use anyOf
        $this->assertArrayHasKey('anyOf', $props['name']);
        $this->assertSame('string', $props['name']['anyOf'][0]['type']);
        $this->assertSame('null', $props['name']['anyOf'][1]['type']);

        // Boolean with default
        $this->assertSame('boolean', $props['active']['type']);
        $this->assertTrue($props['active']['default']);
    }

    #[Test]
    public function it_generates_from_class_and_method_name(): void
    {
        $definition = $this->generator->fromClassMethod(
            ToolTestFixture::class,
            'getWeather',
        );

        $this->assertNotNull($definition);
        $this->assertSame('getWeather', $definition->name);
    }

    #[Test]
    public function it_creates_tool_definition_from_array(): void
    {
        $data = [
            'name' => 'test_tool',
            'description' => 'A test tool',
            'parameters' => [
                'type' => 'object',
                'properties' => [
                    'input' => ['type' => 'string'],
                ],
            ],
        ];

        $definition = ToolDefinition::fromArray($data);

        $this->assertSame('test_tool', $definition->name);
        $this->assertSame('A test tool', $definition->description);
        $this->assertSame('object', $definition->parameters['type']);
    }

    #[Test]
    public function it_converts_tool_definition_to_array(): void
    {
        $definition = new ToolDefinition(
            name: 'my_tool',
            description: 'My tool description',
            parameters: ['type' => 'object', 'properties' => []],
        );

        $array = $definition->toArray();

        $this->assertSame('my_tool', $array['name']);
        $this->assertSame('My tool description', $array['description']);
        $this->assertSame(['type' => 'object', 'properties' => []], $array['parameters']);
    }
}
