<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit;

use Lattice\Validation\Tests\Fixtures\CreateUserDto;
use Lattice\Validation\Tests\Fixtures\FullFeaturedDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(Validator::class)]
final class ValidatorTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function it_validates_a_valid_dto(): void
    {
        $dto = new CreateUserDto(name: 'Alice', email: 'alice@example.com', age: 30);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->getErrors());
    }

    #[Test]
    public function it_validates_valid_dto_with_null_optional_field(): void
    {
        $dto = new CreateUserDto(name: 'Alice', email: 'alice@example.com');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function it_fails_on_required_empty_string(): void
    {
        $dto = new CreateUserDto(name: '', email: 'alice@example.com');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $errors = $result->getErrors();
        self::assertSame('name', $errors[0]->field);
        self::assertSame('Required', $errors[0]->rule);
    }

    #[Test]
    public function it_fails_on_string_too_short(): void
    {
        $dto = new CreateUserDto(name: 'A', email: 'alice@example.com');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $errors = $result->getErrors();
        self::assertSame('name', $errors[0]->field);
        self::assertSame('StringType', $errors[0]->rule);
    }

    #[Test]
    public function it_fails_on_string_too_long(): void
    {
        $dto = new CreateUserDto(name: str_repeat('A', 101), email: 'alice@example.com');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $errors = $result->getErrors();
        self::assertSame('name', $errors[0]->field);
        self::assertSame('StringType', $errors[0]->rule);
    }

    #[Test]
    public function it_fails_on_invalid_email(): void
    {
        $dto = new CreateUserDto(name: 'Alice', email: 'not-an-email');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $errors = $result->getErrors();
        self::assertSame('email', $errors[0]->field);
        self::assertSame('Email', $errors[0]->rule);
    }

    #[Test]
    public function it_fails_on_integer_below_min(): void
    {
        $dto = new CreateUserDto(name: 'Alice', email: 'alice@example.com', age: -1);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $errors = $result->getErrors();
        self::assertSame('age', $errors[0]->field);
        self::assertSame('IntegerType', $errors[0]->rule);
    }

    #[Test]
    public function it_fails_on_integer_above_max(): void
    {
        $dto = new CreateUserDto(name: 'Alice', email: 'alice@example.com', age: 200);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $errors = $result->getErrors();
        self::assertSame('age', $errors[0]->field);
        self::assertSame('IntegerType', $errors[0]->rule);
    }

    #[Test]
    public function it_collects_multiple_errors(): void
    {
        $dto = new CreateUserDto(name: '', email: 'bad');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertGreaterThanOrEqual(2, count($result->getErrors()));
    }

    #[Test]
    public function it_validates_url_attribute(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            website: 'not-a-url',
        );
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $fieldNames = array_map(fn($e) => $e->field, $result->getErrors());
        self::assertContains('website', $fieldNames);
    }

    #[Test]
    public function it_validates_valid_url(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            website: 'https://example.com',
        );
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function it_validates_uuid_attribute(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            externalId: 'not-a-uuid',
        );
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $fieldNames = array_map(fn($e) => $e->field, $result->getErrors());
        self::assertContains('externalId', $fieldNames);
    }

    #[Test]
    public function it_validates_valid_uuid(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            externalId: '550e8400-e29b-41d4-a716-446655440000',
        );
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function it_validates_float_range(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            price: 1000.00,
        );
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $fieldNames = array_map(fn($e) => $e->field, $result->getErrors());
        self::assertContains('price', $fieldNames);
    }

    #[Test]
    public function it_validates_boolean_type(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            active: true,
        );
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function it_validates_in_array(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            role: 'superadmin',
        );
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $fieldNames = array_map(fn($e) => $e->field, $result->getErrors());
        self::assertContains('role', $fieldNames);
    }

    #[Test]
    public function it_validates_valid_in_array_value(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            role: 'admin',
        );
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function it_validates_array_type_min_items(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            tags: [],
        );
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $fieldNames = array_map(fn($e) => $e->field, $result->getErrors());
        self::assertContains('tags', $fieldNames);
    }

    #[Test]
    public function it_validates_array_type_max_items(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            tags: ['a', 'b', 'c', 'd', 'e', 'f'],
        );
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }

    #[Test]
    public function it_validates_datetime_type(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            birthDate: 'not-a-date',
        );
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $fieldNames = array_map(fn($e) => $e->field, $result->getErrors());
        self::assertContains('birthDate', $fieldNames);
    }

    #[Test]
    public function it_validates_valid_datetime(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            birthDate: '1990-05-15',
        );
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function it_allows_null_when_nullable_attribute_present(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            nickname: null,
        );
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function it_validates_string_pattern(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            countryCode: 'usa',
        );
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        $fieldNames = array_map(fn($e) => $e->field, $result->getErrors());
        self::assertContains('countryCode', $fieldNames);
    }

    #[Test]
    public function it_validates_valid_pattern(): void
    {
        $dto = new FullFeaturedDto(
            name: 'Bob',
            email: 'bob@example.com',
            countryCode: 'US',
        );
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }
}
