<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit;

use Lattice\Validation\Exceptions\ValidationException;
use Lattice\Validation\Tests\Fixtures\CreateUserDto;
use Lattice\Validation\ValidationPipe;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationPipe::class)]
final class ValidationPipeTest extends TestCase
{
    private ValidationPipe $pipe;

    protected function setUp(): void
    {
        $this->pipe = new ValidationPipe();
    }

    #[Test]
    public function it_passes_valid_dto_through(): void
    {
        $dto = new CreateUserDto(name: 'Alice', email: 'alice@example.com', age: 30);

        $result = $this->pipe->transform($dto);

        self::assertSame($dto, $result);
    }

    #[Test]
    public function it_throws_on_invalid_dto(): void
    {
        $dto = new CreateUserDto(name: '', email: 'bad');

        $this->expectException(ValidationException::class);

        $this->pipe->transform($dto);
    }

    #[Test]
    public function it_passes_non_objects_through_unchanged(): void
    {
        $value = 'just a string';

        $result = $this->pipe->transform($value);

        self::assertSame($value, $result);
    }

    #[Test]
    public function it_passes_objects_without_validation_attributes_through(): void
    {
        $obj = new \stdClass();

        $result = $this->pipe->transform($obj);

        self::assertSame($obj, $result);
    }

    #[Test]
    public function validation_exception_contains_result(): void
    {
        $dto = new CreateUserDto(name: '', email: 'bad');

        try {
            $this->pipe->transform($dto);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $result = $e->getValidationResult();
            self::assertFalse($result->isValid());
            self::assertNotEmpty($result->getErrors());
        }
    }
}
