<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\IntegerTypeDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IntegerTypeTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_integer_passes(): void
    {
        $dto = new IntegerTypeDto(value: 42);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_string_fails(): void
    {
        $dto = new IntegerTypeDto(value: 'not-an-int');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertSame('IntegerType', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_float_fails(): void
    {
        $dto = new IntegerTypeDto(value: 3.14);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }

    #[Test]
    public function test_zero_passes(): void
    {
        $dto = new IntegerTypeDto(value: 0);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_negative_integer_passes(): void
    {
        $dto = new IntegerTypeDto(value: -10);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }
}
