<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\StringTypeDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StringTypeTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_string_passes(): void
    {
        $dto = new StringTypeDto(value: 'hello');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_integer_fails(): void
    {
        $dto = new StringTypeDto(value: 42);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertSame('StringType', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_null_is_skipped(): void
    {
        $dto = new StringTypeDto(value: null);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_empty_string_passes(): void
    {
        $dto = new StringTypeDto(value: '');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }
}
