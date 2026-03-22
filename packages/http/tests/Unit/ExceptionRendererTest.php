<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit;

use Lattice\Http\Exception\BadRequestException;
use Lattice\Http\Exception\ForbiddenException;
use Lattice\Http\Exception\HttpException;
use Lattice\Http\Exception\NotFoundException;
use Lattice\Http\Exception\UnauthorizedException;
use Lattice\Http\ExceptionRenderer;
use Lattice\Http\Response;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(ExceptionRenderer::class)]
final class ExceptionRendererTest extends TestCase
{
    #[Test]
    public function http_exception_returns_correct_status_code(): void
    {
        $renderer = new ExceptionRenderer();
        $response = $renderer->render(new HttpException('Conflict', 409));

        self::assertSame(409, $response->statusCode);
        self::assertSame('Conflict', $response->body['title']);
        self::assertSame(409, $response->body['status']);
        self::assertSame('https://httpstatuses.io/409', $response->body['type']);
        self::assertSame('Conflict', $response->body['detail']);
    }

    #[Test]
    public function not_found_exception_returns_404(): void
    {
        $renderer = new ExceptionRenderer();
        $response = $renderer->render(new NotFoundException('User not found'));

        self::assertSame(404, $response->statusCode);
        self::assertSame('Not Found', $response->body['title']);
        self::assertSame('User not found', $response->body['detail']);
    }

    #[Test]
    public function bad_request_exception_returns_400(): void
    {
        $renderer = new ExceptionRenderer();
        $response = $renderer->render(new BadRequestException('Invalid input'));

        self::assertSame(400, $response->statusCode);
        self::assertSame('Invalid input', $response->body['detail']);
    }

    #[Test]
    public function unauthorized_exception_returns_401(): void
    {
        $renderer = new ExceptionRenderer();
        $response = $renderer->render(new UnauthorizedException());

        self::assertSame(401, $response->statusCode);
        self::assertSame('Unauthorized', $response->body['title']);
    }

    #[Test]
    public function forbidden_exception_returns_403(): void
    {
        $renderer = new ExceptionRenderer();
        $response = $renderer->render(new ForbiddenException());

        self::assertSame(403, $response->statusCode);
        self::assertSame('Forbidden', $response->body['title']);
    }

    #[Test]
    public function generic_exception_returns_500(): void
    {
        $renderer = new ExceptionRenderer();
        $response = $renderer->render(new \RuntimeException('Something broke'));

        self::assertSame(500, $response->statusCode);
        self::assertSame('Internal Server Error', $response->body['title']);
        self::assertSame(500, $response->body['status']);
        self::assertSame('An unexpected error occurred.', $response->body['detail']);
    }

    #[Test]
    public function invalid_argument_exception_returns_400(): void
    {
        $renderer = new ExceptionRenderer();
        $response = $renderer->render(new \InvalidArgumentException('Bad arg'));

        self::assertSame(400, $response->statusCode);
        self::assertSame('Bad Request', $response->body['title']);
        self::assertSame('Bad arg', $response->body['detail']);
    }

    #[Test]
    public function debug_mode_includes_stack_trace(): void
    {
        $renderer = new ExceptionRenderer(debug: true);
        $response = $renderer->render(new \RuntimeException('Debug me'));

        self::assertArrayHasKey('debug', $response->body);
        self::assertSame(\RuntimeException::class, $response->body['debug']['exception']);
        self::assertArrayHasKey('file', $response->body['debug']);
        self::assertArrayHasKey('line', $response->body['debug']);
        self::assertArrayHasKey('trace', $response->body['debug']);
        self::assertLessThanOrEqual(10, count($response->body['debug']['trace']));
    }

    #[Test]
    public function production_mode_hides_stack_trace(): void
    {
        $renderer = new ExceptionRenderer(debug: false);
        $response = $renderer->render(new \RuntimeException('Secret info'));

        self::assertArrayNotHasKey('debug', $response->body);
        // Internal exception message should not leak
        self::assertStringNotContainsString('Secret info', $response->body['detail']);
    }

    #[Test]
    public function response_has_problem_json_content_type(): void
    {
        $renderer = new ExceptionRenderer();
        $response = $renderer->render(new \RuntimeException('test'));

        self::assertSame('application/problem+json', $response->headers['Content-Type']);
    }

    #[Test]
    public function response_includes_instance_identifier(): void
    {
        $renderer = new ExceptionRenderer();
        $response = $renderer->render(new \RuntimeException('test'));

        self::assertArrayHasKey('instance', $response->body);
        self::assertStringStartsWith('err-', $response->body['instance']);
    }

    #[Test]
    public function exception_is_logged_with_context(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Something broke',
                self::callback(function (array $context): bool {
                    return $context['exception'] === \RuntimeException::class
                        && isset($context['file'])
                        && isset($context['line']);
                }),
            );

        $renderer = new ExceptionRenderer(logger: $logger);
        $renderer->render(new \RuntimeException('Something broke'));
    }

    #[Test]
    public function client_error_logged_as_warning(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Not Found',
                self::anything(),
            );

        $renderer = new ExceptionRenderer(logger: $logger);
        $renderer->render(new NotFoundException('Not Found'));
    }

    #[Test]
    public function server_error_logged_as_error(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with(
                'Crash',
                self::anything(),
            );

        $renderer = new ExceptionRenderer(logger: $logger);
        $renderer->render(new \RuntimeException('Crash'));
    }

    #[Test]
    public function no_logger_does_not_throw(): void
    {
        $renderer = new ExceptionRenderer(logger: null);
        $response = $renderer->render(new \RuntimeException('No logger'));

        self::assertSame(500, $response->statusCode);
    }
}
