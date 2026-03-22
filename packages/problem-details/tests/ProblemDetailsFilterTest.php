<?php

declare(strict_types=1);

namespace Lattice\ProblemDetails\Tests;

use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Pipeline\ExceptionFilterInterface;
use Lattice\Http\Response;
use Lattice\ProblemDetails\ProblemDetails;
use Lattice\ProblemDetails\ProblemDetailsException;
use Lattice\ProblemDetails\ProblemDetailsFilter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProblemDetailsFilterTest extends TestCase
{
    private ProblemDetailsFilter $filter;
    private ExecutionContextInterface $context;

    protected function setUp(): void
    {
        $this->filter = new ProblemDetailsFilter();

        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->context->method('getCorrelationId')->willReturn('test-corr-id');
    }

    #[Test]
    public function it_implements_exception_filter_interface(): void
    {
        $this->assertInstanceOf(ExceptionFilterInterface::class, $this->filter);
    }

    #[Test]
    public function it_converts_problem_details_exception_to_array(): void
    {
        $problem = ProblemDetails::badRequest('Invalid input');
        $exception = new ProblemDetailsException($problem);

        $result = $this->filter->catch($exception, $this->context);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(400, $result->statusCode);
        $body = $result->body;
        $this->assertIsArray($body);
        $this->assertSame(400, $body['status']);
        $this->assertSame('Bad Request', $body['title']);
        $this->assertSame('Invalid input', $body['detail']);
    }

    #[Test]
    public function it_sets_correlation_id_from_context(): void
    {
        $problem = ProblemDetails::notFound('Resource not found');
        $exception = new ProblemDetailsException($problem);

        $result = $this->filter->catch($exception, $this->context);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('test-corr-id', $result->body['correlationId']);
    }

    #[Test]
    public function it_converts_generic_exception_to_internal_error(): void
    {
        $exception = new \RuntimeException('Something broke');

        $result = $this->filter->catch($exception, $this->context);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(500, $result->statusCode);
        $body = $result->body;
        $this->assertIsArray($body);
        $this->assertSame(500, $body['status']);
        $this->assertSame('Internal Server Error', $body['title']);
        $this->assertSame('test-corr-id', $body['correlationId']);
    }

    #[Test]
    public function generic_exception_does_not_leak_internal_details(): void
    {
        $exception = new \RuntimeException('Database connection failed on host 192.168.1.1');

        $result = $this->filter->catch($exception, $this->context);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame('Internal Server Error', $result->body['detail']);
    }

    #[Test]
    public function it_preserves_extensions_from_problem_details(): void
    {
        $problem = ProblemDetails::unprocessableEntity('Validation failed', ['name' => 'Required']);
        $exception = new ProblemDetailsException($problem);

        $result = $this->filter->catch($exception, $this->context);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertSame(['name' => 'Required'], $result->body['field_errors']);
    }
}
