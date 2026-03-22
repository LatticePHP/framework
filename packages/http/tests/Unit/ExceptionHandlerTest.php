<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit;

use Lattice\Http\ExceptionHandler;
use Lattice\Http\Response;
use Lattice\Http\Exception\HttpException;
use Lattice\Http\Exception\NotFoundException;
use Lattice\Http\Exception\BadRequestException;
use Lattice\Http\Exception\UnauthorizedException;
use Lattice\Http\Exception\ForbiddenException;
use PHPUnit\Framework\TestCase;

final class ExceptionHandlerTest extends TestCase
{
    private ExceptionHandler $handler;

    protected function setUp(): void
    {
        $this->handler = new ExceptionHandler();
    }

    public function testHandleNotFoundException(): void
    {
        $exception = new NotFoundException('User not found');
        $response = $this->handler->handle($exception);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(404, $response->statusCode);
        $this->assertSame('User not found', $response->body['error']);
    }

    public function testHandleBadRequestException(): void
    {
        $exception = new BadRequestException('Invalid input');
        $response = $this->handler->handle($exception);

        $this->assertSame(400, $response->statusCode);
        $this->assertSame('Invalid input', $response->body['error']);
    }

    public function testHandleUnauthorizedException(): void
    {
        $exception = new UnauthorizedException('Not authenticated');
        $response = $this->handler->handle($exception);

        $this->assertSame(401, $response->statusCode);
        $this->assertSame('Not authenticated', $response->body['error']);
    }

    public function testHandleForbiddenException(): void
    {
        $exception = new ForbiddenException('Access denied');
        $response = $this->handler->handle($exception);

        $this->assertSame(403, $response->statusCode);
        $this->assertSame('Access denied', $response->body['error']);
    }

    public function testHandleGenericHttpException(): void
    {
        $exception = new HttpException('Conflict', 409);
        $response = $this->handler->handle($exception);

        $this->assertSame(409, $response->statusCode);
        $this->assertSame('Conflict', $response->body['error']);
    }

    public function testHandleUnknownExceptionReturns500(): void
    {
        $exception = new \RuntimeException('Something broke');
        $response = $this->handler->handle($exception);

        $this->assertSame(500, $response->statusCode);
        $this->assertSame('Internal Server Error', $response->body['error']);
    }

    public function testResponseIsJson(): void
    {
        $exception = new NotFoundException('Not found');
        $response = $this->handler->handle($exception);

        $this->assertSame('application/json', $response->headers['Content-Type']);
    }
}
