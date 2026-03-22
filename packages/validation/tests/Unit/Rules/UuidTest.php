<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\UuidDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class UuidTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_valid_uuid_passes(): void
    {
        $dto = new UuidDto(value: '550e8400-e29b-41d4-a716-446655440000');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_invalid_uuid_fails(): void
    {
        $dto = new UuidDto(value: 'not-a-uuid');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertSame('Uuid', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_uppercase_uuid_passes(): void
    {
        $dto = new UuidDto(value: '550E8400-E29B-41D4-A716-446655440000');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_integer_fails(): void
    {
        $dto = new UuidDto(value: 12345);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }
}
