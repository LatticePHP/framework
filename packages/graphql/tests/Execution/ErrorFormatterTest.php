<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Tests\Execution;

use Lattice\GraphQL\Execution\ErrorFormatter;
use Lattice\GraphQL\Execution\GraphqlException;
use PHPUnit\Framework\TestCase;

final class ErrorFormatterTest extends TestCase
{
    public function test_format_exception_in_production_mode(): void
    {
        $formatter = new ErrorFormatter(debug: false);
        $exception = new \RuntimeException('Database connection failed');

        $error = $formatter->format($exception);

        $this->assertSame('Internal server error', $error['message']);
        $this->assertArrayNotHasKey('extensions', $error);
    }

    public function test_format_graphql_exception_exposes_message_in_production(): void
    {
        $formatter = new ErrorFormatter(debug: false);
        $exception = new GraphqlException('User not found');

        $error = $formatter->format($exception);

        $this->assertSame('User not found', $error['message']);
    }

    public function test_format_exception_in_debug_mode(): void
    {
        $formatter = new ErrorFormatter(debug: true);
        $exception = new \RuntimeException('Database connection failed');

        $error = $formatter->format($exception);

        $this->assertSame('Database connection failed', $error['message']);
        $this->assertArrayHasKey('extensions', $error);
        $this->assertSame('Database connection failed', $error['extensions']['debugMessage']);
        $this->assertSame(\RuntimeException::class, $error['extensions']['exception']);
        $this->assertArrayHasKey('file', $error['extensions']);
        $this->assertArrayHasKey('line', $error['extensions']);
        $this->assertArrayHasKey('trace', $error['extensions']);
    }

    public function test_format_with_path(): void
    {
        $formatter = new ErrorFormatter(debug: false);
        $exception = new GraphqlException('Field error');

        $error = $formatter->format($exception, path: [0, 1]);

        $this->assertSame([0, 1], $error['path']);
    }

    public function test_format_message(): void
    {
        $formatter = new ErrorFormatter();

        $error = $formatter->formatMessage('Validation failed');

        $this->assertSame('Validation failed', $error['message']);
        $this->assertArrayNotHasKey('path', $error);
        $this->assertArrayNotHasKey('locations', $error);
    }

    public function test_format_message_with_path_and_locations(): void
    {
        $formatter = new ErrorFormatter();

        $error = $formatter->formatMessage(
            'Validation failed',
            path: [0],
            locations: [['line' => 1, 'column' => 5]],
        );

        $this->assertSame([0], $error['path']);
        $this->assertSame([['line' => 1, 'column' => 5]], $error['locations']);
    }

    public function test_format_all(): void
    {
        $formatter = new ErrorFormatter(debug: false);

        $errors = $formatter->formatAll([
            new GraphqlException('Error 1'),
            new GraphqlException('Error 2'),
        ]);

        $this->assertCount(2, $errors);
        $this->assertSame('Error 1', $errors[0]['message']);
        $this->assertSame('Error 2', $errors[1]['message']);
    }

    public function test_graphql_exception_with_extensions(): void
    {
        $exception = new GraphqlException(
            'Validation failed',
            extensions: ['code' => 'VALIDATION_ERROR', 'field' => 'email'],
        );

        $this->assertSame('Validation failed', $exception->getMessage());
        $this->assertSame('VALIDATION_ERROR', $exception->extensions['code']);
        $this->assertSame('email', $exception->extensions['field']);
    }

    public function test_debug_trace_format(): void
    {
        $formatter = new ErrorFormatter(debug: true);

        try {
            throw new \RuntimeException('Test');
        } catch (\RuntimeException $e) {
            $error = $formatter->format($e);
        }

        $this->assertIsArray($error['extensions']['trace']);
        $this->assertNotEmpty($error['extensions']['trace']);

        $firstEntry = $error['extensions']['trace'][0];
        $this->assertArrayHasKey('file', $firstEntry);
        $this->assertArrayHasKey('line', $firstEntry);
        $this->assertArrayHasKey('call', $firstEntry);
    }

    public function test_format_with_empty_path_omits_path(): void
    {
        $formatter = new ErrorFormatter(debug: false);
        $exception = new GraphqlException('Error');

        $error = $formatter->format($exception, path: []);

        $this->assertArrayNotHasKey('path', $error);
    }
}
