<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit;

use Lattice\Validation\DtoMapper;
use Lattice\Validation\Exceptions\MappingException;
use Lattice\Validation\Tests\Fixtures\CreateUserDto;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DtoMapper::class)]
final class DtoMapperTest extends TestCase
{
    private DtoMapper $mapper;

    protected function setUp(): void
    {
        $this->mapper = new DtoMapper();
    }

    #[Test]
    public function it_maps_array_to_dto(): void
    {
        $data = [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'age' => 30,
        ];

        $dto = $this->mapper->map($data, CreateUserDto::class);

        self::assertInstanceOf(CreateUserDto::class, $dto);
        self::assertSame('Alice', $dto->name);
        self::assertSame('alice@example.com', $dto->email);
        self::assertSame(30, $dto->age);
    }

    #[Test]
    public function it_handles_optional_fields_with_defaults(): void
    {
        $data = [
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ];

        $dto = $this->mapper->map($data, CreateUserDto::class);

        self::assertNull($dto->age);
    }

    #[Test]
    public function it_coerces_string_to_int(): void
    {
        $data = [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'age' => '30',
        ];

        $dto = $this->mapper->map($data, CreateUserDto::class);

        self::assertSame(30, $dto->age);
    }

    #[Test]
    public function it_ignores_extra_fields(): void
    {
        $data = [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'unknown_field' => 'ignored',
        ];

        $dto = $this->mapper->map($data, CreateUserDto::class);

        self::assertInstanceOf(CreateUserDto::class, $dto);
        self::assertSame('Alice', $dto->name);
    }

    #[Test]
    public function it_throws_on_missing_required_constructor_param(): void
    {
        $this->expectException(MappingException::class);

        $data = [
            'name' => 'Alice',
            // missing email
        ];

        $this->mapper->map($data, CreateUserDto::class);
    }

    #[Test]
    public function it_throws_on_invalid_class(): void
    {
        $this->expectException(MappingException::class);

        $this->mapper->map([], 'NonExistent\\Class');
    }

    #[Test]
    public function it_coerces_string_to_float(): void
    {
        // We need a DTO with a float field — use FullFeaturedDto
        $data = [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'price' => '19.99',
        ];

        $dto = $this->mapper->map($data, \Lattice\Validation\Tests\Fixtures\FullFeaturedDto::class);

        self::assertSame(19.99, $dto->price);
    }

    #[Test]
    public function it_coerces_string_to_bool(): void
    {
        $data = [
            'name' => 'Bob',
            'email' => 'bob@example.com',
            'active' => 'true',
        ];

        $dto = $this->mapper->map($data, \Lattice\Validation\Tests\Fixtures\FullFeaturedDto::class);

        self::assertTrue($dto->active);
    }
}
