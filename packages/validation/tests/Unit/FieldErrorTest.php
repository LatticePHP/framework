<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit;

use Lattice\Validation\FieldError;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(FieldError::class)]
final class FieldErrorTest extends TestCase
{
    #[Test]
    public function it_stores_field_message_and_rule(): void
    {
        $error = new FieldError(
            field: 'email',
            message: 'Invalid email address',
            rule: 'Email',
        );

        self::assertSame('email', $error->field);
        self::assertSame('Invalid email address', $error->message);
        self::assertSame('Email', $error->rule);
        self::assertNull($error->value);
    }

    #[Test]
    public function it_stores_optional_value_for_debugging(): void
    {
        $error = new FieldError(
            field: 'age',
            message: 'Must be at least 0',
            rule: 'IntegerType',
            value: -5,
        );

        self::assertSame('age', $error->field);
        self::assertSame(-5, $error->value);
    }

    #[Test]
    public function it_converts_to_array(): void
    {
        $error = new FieldError(
            field: 'name',
            message: 'Field is required',
            rule: 'Required',
            value: '',
        );

        $array = $error->toArray();

        self::assertSame('name', $array['field']);
        self::assertSame('Field is required', $array['message']);
        self::assertSame('Required', $array['rule']);
    }
}
