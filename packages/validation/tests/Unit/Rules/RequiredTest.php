<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\RequiredDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RequiredTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_null_value_fails(): void
    {
        $dto = new RequiredDto(value: null);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertCount(1, $result->getErrors());
        self::assertSame('value', $result->getErrors()[0]->field);
        self::assertSame('Required', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_empty_string_fails(): void
    {
        $dto = new RequiredDto(value: '');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }

    #[Test]
    public function test_empty_array_fails(): void
    {
        $dto = new RequiredDto(value: []);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }

    #[Test]
    public function test_non_empty_string_passes(): void
    {
        $dto = new RequiredDto(value: 'hello');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_zero_passes(): void
    {
        $dto = new RequiredDto(value: 0);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_false_passes(): void
    {
        $dto = new RequiredDto(value: false);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }
}
