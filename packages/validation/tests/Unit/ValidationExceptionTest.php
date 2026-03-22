<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit;

use Lattice\Validation\Exceptions\ValidationException;
use Lattice\Validation\FieldError;
use Lattice\Validation\ValidationResult;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class ValidationExceptionTest extends TestCase
{
    #[Test]
    public function test_create_with_validation_result(): void
    {
        $errors = [
            new FieldError('name', 'The name field is required', 'Required'),
            new FieldError('email', 'The email field must be a valid email address', 'Email'),
        ];

        $result = new ValidationResult($errors);
        $exception = new ValidationException($result);

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('Validation failed', $exception->getMessage());
        self::assertSame($result, $exception->getValidationResult());
    }

    #[Test]
    public function test_get_errors_via_validation_result(): void
    {
        $errors = [
            new FieldError('age', 'The age field must be an integer', 'IntegerType'),
        ];

        $result = new ValidationResult($errors);
        $exception = new ValidationException($result);

        $retrievedErrors = $exception->getValidationResult()->getErrors();

        self::assertCount(1, $retrievedErrors);
        self::assertSame('age', $retrievedErrors[0]->field);
        self::assertSame('IntegerType', $retrievedErrors[0]->rule);
    }

    #[Test]
    public function test_custom_message(): void
    {
        $result = new ValidationResult([]);
        $exception = new ValidationException($result, 'Custom validation error');

        self::assertSame('Custom validation error', $exception->getMessage());
    }

    #[Test]
    public function test_empty_validation_result(): void
    {
        $result = new ValidationResult([]);
        $exception = new ValidationException($result);

        self::assertTrue($exception->getValidationResult()->isValid());
        self::assertSame([], $exception->getValidationResult()->getErrors());
    }
}
