<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\NullableDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class NullableTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_null_allowed_when_nullable(): void
    {
        $dto = new NullableDto(value: null);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_valid_string_passes_with_nullable(): void
    {
        $dto = new NullableDto(value: 'hello');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_invalid_type_still_fails_with_nullable(): void
    {
        $dto = new NullableDto(value: 42);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }
}
