<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\DateTimeTypeDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class DateTimeTypeTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_valid_datetime_passes(): void
    {
        $dto = new DateTimeTypeDto(value: '2025-01-15T10:30:00+00:00');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_invalid_datetime_fails(): void
    {
        $dto = new DateTimeTypeDto(value: 'not-a-date');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertSame('DateTimeType', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_integer_fails(): void
    {
        $dto = new DateTimeTypeDto(value: 12345);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }

    #[Test]
    public function test_null_is_skipped(): void
    {
        $dto = new DateTimeTypeDto(value: null);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }
}
