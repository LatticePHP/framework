<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\ArrayTypeDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ArrayTypeTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_array_passes(): void
    {
        $dto = new ArrayTypeDto(value: [1, 2, 3]);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_string_fails(): void
    {
        $dto = new ArrayTypeDto(value: 'not-an-array');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertSame('ArrayType', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_empty_array_passes(): void
    {
        $dto = new ArrayTypeDto(value: []);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_integer_fails(): void
    {
        $dto = new ArrayTypeDto(value: 42);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }
}
