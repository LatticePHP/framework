<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\FloatTypeDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FloatTypeTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_float_passes(): void
    {
        $dto = new FloatTypeDto(value: 3.14);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_integer_passes_as_number(): void
    {
        $dto = new FloatTypeDto(value: 42);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_string_fails(): void
    {
        $dto = new FloatTypeDto(value: 'not-a-float');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertSame('FloatType', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_null_is_skipped(): void
    {
        $dto = new FloatTypeDto(value: null);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }
}
