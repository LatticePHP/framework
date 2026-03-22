<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\BooleanTypeDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BooleanTypeTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_true_passes(): void
    {
        $dto = new BooleanTypeDto(value: true);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_false_passes(): void
    {
        $dto = new BooleanTypeDto(value: false);
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_integer_fails(): void
    {
        $dto = new BooleanTypeDto(value: 1);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertSame('BooleanType', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_string_fails(): void
    {
        $dto = new BooleanTypeDto(value: 'true');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }
}
