<?php

declare(strict_types=1);

namespace Lattice\OpenApi\Tests\Unit;

use Lattice\OpenApi\Schema\SchemaGenerator;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

// Test DTO stubs
final class StringPropertyDto
{
    public function __construct(
        public readonly string $name,
    ) {}
}

final class AllTypesDto
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
        public readonly float $score,
        public readonly bool $active,
        public readonly array $tags,
    ) {}
}

final class NullablePropertyDto
{
    public function __construct(
        public readonly ?string $nickname,
        public readonly ?int $age,
    ) {}
}

enum StatusEnum: string
{
    case Active = 'active';
    case Inactive = 'inactive';
}

final class EnumPropertyDto
{
    public function __construct(
        public readonly StatusEnum $status,
    ) {}
}

final class NoConstructorDto
{
    public string $name;
    public int $age;
}

#[CoversClass(SchemaGenerator::class)]
final class SchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SchemaGenerator();
    }

    #[Test]
    public function it_generates_schema_for_string_property(): void
    {
        $schema = $this->generator->fromClass(StringPropertyDto::class);

        $this->assertSame('object', $schema['type']);
        $this->assertSame('string', $schema['properties']['name']['type']);
        $this->assertContains('name', $schema['required']);
    }

    #[Test]
    public function it_generates_schema_for_all_scalar_types(): void
    {
        $schema = $this->generator->fromClass(AllTypesDto::class);

        $this->assertSame('string', $schema['properties']['name']['type']);
        $this->assertSame('integer', $schema['properties']['age']['type']);
        $this->assertSame('number', $schema['properties']['score']['type']);
        $this->assertSame('boolean', $schema['properties']['active']['type']);
        $this->assertSame('array', $schema['properties']['tags']['type']);
    }

    #[Test]
    public function it_handles_nullable_properties(): void
    {
        $schema = $this->generator->fromClass(NullablePropertyDto::class);

        $this->assertContains('string', $schema['properties']['nickname']['type']);
        $this->assertContains('null', $schema['properties']['nickname']['type']);
        $this->assertNotContains('nickname', $schema['required'] ?? []);
    }

    #[Test]
    public function it_handles_enum_properties(): void
    {
        $schema = $this->generator->fromClass(EnumPropertyDto::class);

        $this->assertSame('string', $schema['properties']['status']['type']);
        $this->assertSame(['active', 'inactive'], $schema['properties']['status']['enum']);
    }

    #[Test]
    public function it_reads_public_properties_without_constructor(): void
    {
        $schema = $this->generator->fromClass(NoConstructorDto::class);

        $this->assertSame('object', $schema['type']);
        $this->assertArrayHasKey('name', $schema['properties']);
        $this->assertArrayHasKey('age', $schema['properties']);
    }

    #[Test]
    public function it_throws_for_nonexistent_class(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->generator->fromClass('NonExistent\\ClassName');
    }
}
