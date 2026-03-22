<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit\Rules;

use Lattice\Validation\Tests\Fixtures\EmailDto;
use Lattice\Validation\Validator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class EmailTest extends TestCase
{
    private Validator $validator;

    protected function setUp(): void
    {
        $this->validator = new Validator();
    }

    #[Test]
    public function test_valid_email_passes(): void
    {
        $dto = new EmailDto(value: 'user@example.com');
        $result = $this->validator->validate($dto);

        self::assertTrue($result->isValid());
    }

    #[Test]
    public function test_invalid_email_fails(): void
    {
        $dto = new EmailDto(value: 'not-an-email');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
        self::assertSame('Email', $result->getErrors()[0]->rule);
    }

    #[Test]
    public function test_empty_string_fails(): void
    {
        $dto = new EmailDto(value: '');
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }

    #[Test]
    public function test_integer_fails(): void
    {
        $dto = new EmailDto(value: 123);
        $result = $this->validator->validate($dto);

        self::assertFalse($result->isValid());
    }
}
