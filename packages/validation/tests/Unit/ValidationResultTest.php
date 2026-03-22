<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit;

use Lattice\Validation\FieldError;
use Lattice\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ValidationResult::class)]
final class ValidationResultTest extends TestCase
{
    #[Test]
    public function it_is_valid_when_no_errors(): void
    {
        $result = new ValidationResult([]);

        self::assertTrue($result->isValid());
        self::assertSame([], $result->getErrors());
    }

    #[Test]
    public function it_is_invalid_when_errors_exist(): void
    {
        $errors = [
            new FieldError('name', 'Field is required', 'Required'),
        ];
        $result = new ValidationResult($errors);

        self::assertFalse($result->isValid());
        self::assertCount(1, $result->getErrors());
    }

    #[Test]
    public function it_returns_field_errors(): void
    {
        $errors = [
            new FieldError('name', 'Field is required', 'Required'),
            new FieldError('email', 'Invalid email', 'Email'),
        ];
        $result = new ValidationResult($errors);

        $returned = $result->getErrors();
        self::assertCount(2, $returned);
        self::assertSame('name', $returned[0]->field);
        self::assertSame('email', $returned[1]->field);
    }

    #[Test]
    public function to_array_returns_machine_readable_format(): void
    {
        $errors = [
            new FieldError('name', 'Field is required', 'Required'),
            new FieldError('email', 'Invalid email', 'Email', 'not-an-email'),
        ];
        $result = new ValidationResult($errors);

        $array = $result->toArray();

        self::assertArrayHasKey('valid', $array);
        self::assertFalse($array['valid']);
        self::assertArrayHasKey('errors', $array);
        self::assertCount(2, $array['errors']);
        self::assertSame('name', $array['errors'][0]['field']);
        self::assertSame('email', $array['errors'][1]['field']);
    }

    #[Test]
    public function to_array_for_valid_result(): void
    {
        $result = new ValidationResult([]);
        $array = $result->toArray();

        self::assertTrue($array['valid']);
        self::assertSame([], $array['errors']);
    }
}
