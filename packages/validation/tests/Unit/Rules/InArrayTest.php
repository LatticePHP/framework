<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\InArrayDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InArrayTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_value_in_set_passes(): void
    {
        $dto = new InArrayDto(value: 'red');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_value_not_in_set_fails(): void
    {
        $dto = new InArrayDto(value: 'yellow');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertSame('InArray', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_null_is_skipped(): void
    {
        $dto = new InArrayDto(value: null);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_all_valid_values_pass(): void
    {
        foreach (['red', 'green', 'blue'] as $color) {
            $dto = new InArrayDto(value: $color);
            $result = $this->validator->validate($dto);

            self::assertTrue($result->isValid(), "Expected '{$color}' to pass InArray validation");
        }
    }
}
