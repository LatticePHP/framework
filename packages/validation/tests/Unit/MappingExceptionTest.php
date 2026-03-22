<?php

declare(strict_types=1);

namespace Lattice\Validation\Tests\Unit;

use Lattice\Validation\Exceptions\MappingException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class MappingExceptionTest extends TestCase
{
    #[Test]
    public function test_create_with_message(): void
    {
        $exception = new MappingException('Unable to map field "name"');

        self::assertInstanceOf(RuntimeException::class, $exception);
        self::assertSame('Unable to map field "name"', $exception->getMessage());
    }

    #[Test]
    public function test_create_with_code(): void
    {
        $exception = new MappingException('Mapping failed', 422);

        self::assertSame(422, $exception->getCode());
    }

    #[Test]
    public function test_create_with_previous_exception(): void
    {
        $previous = new \InvalidArgumentException('Original error');
        $exception = new MappingException('Mapping failed', 0, $previous);

        self::assertSame($previous, $exception->getPrevious());
    }
}
