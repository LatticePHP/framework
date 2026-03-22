<?php

declare(strict_types=1);

namespace Lattice\ProblemDetails\Tests;

use Lattice\ProblemDetails\ProblemDetails;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ProblemDetailsTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $problem = new ProblemDetails(
            status: 404,
            title: 'Not Found',
        );

        $this->assertSame(404, $problem->status);
        $this->assertSame('Not Found', $problem->title);
        $this->assertNull($problem->type);
        $this->assertNull($problem->detail);
        $this->assertNull($problem->instance);
        $this->assertNull($problem->correlationId);
        $this->assertSame([], $problem->extensions);
    }

    #[Test]
    public function it_creates_with_all_fields(): void
    {
        $problem = new ProblemDetails(
            status: 422,
            title: 'Unprocessable Entity',
            type: 'https://example.com/errors/validation',
            detail: 'Email is invalid',
            instance: '/users/123',
            correlationId: 'abc-123',
            extensions: ['field_errors' => ['email' => 'Invalid format']],
        );

        $this->assertSame(422, $problem->status);
        $this->assertSame('Unprocessable Entity', $problem->title);
        $this->assertSame('https://example.com/errors/validation', $problem->type);
        $this->assertSame('Email is invalid', $problem->detail);
        $this->assertSame('/users/123', $problem->instance);
        $this->assertSame('abc-123', $problem->correlationId);
        $this->assertSame(['field_errors' => ['email' => 'Invalid format']], $problem->extensions);
    }

    #[Test]
    public function json_serialize_produces_rfc9457_format(): void
    {
        $problem = new ProblemDetails(
            status: 400,
            title: 'Bad Request',
            type: 'https://example.com/errors/bad-request',
            detail: 'Missing required field',
            instance: '/orders/456',
            correlationId: 'corr-789',
            extensions: ['field_errors' => ['name' => 'Required']],
        );

        $json = $problem->jsonSerialize();

        $this->assertSame('https://example.com/errors/bad-request', $json['type']);
        $this->assertSame('Bad Request', $json['title']);
        $this->assertSame(400, $json['status']);
        $this->assertSame('Missing required field', $json['detail']);
        $this->assertSame('/orders/456', $json['instance']);
        $this->assertSame('corr-789', $json['correlationId']);
        $this->assertSame(['name' => 'Required'], $json['field_errors']);
    }

    #[Test]
    public function json_serialize_omits_null_fields(): void
    {
        $problem = new ProblemDetails(
            status: 500,
            title: 'Internal Server Error',
        );

        $json = $problem->jsonSerialize();

        $this->assertArrayHasKey('status', $json);
        $this->assertArrayHasKey('title', $json);
        $this->assertArrayNotHasKey('type', $json);
        $this->assertArrayNotHasKey('detail', $json);
        $this->assertArrayNotHasKey('instance', $json);
        $this->assertArrayNotHasKey('correlationId', $json);
    }

    #[Test]
    public function json_serialize_uses_about_blank_default_type(): void
    {
        $problem = new ProblemDetails(
            status: 500,
            title: 'Internal Server Error',
        );

        $json = $problem->jsonSerialize();

        // When type is null, it should be omitted (RFC 9457 says "about:blank" is default)
        $this->assertArrayNotHasKey('type', $json);
    }

    #[Test]
    public function to_array_matches_json_serialize(): void
    {
        $problem = new ProblemDetails(
            status: 403,
            title: 'Forbidden',
            detail: 'Access denied',
        );

        $this->assertSame($problem->jsonSerialize(), $problem->toArray());
    }

    #[Test]
    public function implements_json_serializable(): void
    {
        $problem = new ProblemDetails(status: 200, title: 'OK');

        $this->assertInstanceOf(\JsonSerializable::class, $problem);

        $encoded = json_encode($problem);
        $decoded = json_decode($encoded, true);

        $this->assertSame(200, $decoded['status']);
        $this->assertSame('OK', $decoded['title']);
    }

    #[Test]
    public function factory_bad_request(): void
    {
        $problem = ProblemDetails::badRequest('Invalid input', ['name' => 'Required']);

        $this->assertSame(400, $problem->status);
        $this->assertSame('Bad Request', $problem->title);
        $this->assertSame('Invalid input', $problem->detail);
        $this->assertSame(['name' => 'Required'], $problem->extensions['field_errors']);
    }

    #[Test]
    public function factory_bad_request_without_field_errors(): void
    {
        $problem = ProblemDetails::badRequest('Invalid input');

        $this->assertSame(400, $problem->status);
        $this->assertSame([], $problem->extensions);
    }

    #[Test]
    public function factory_unauthorized(): void
    {
        $problem = ProblemDetails::unauthorized();

        $this->assertSame(401, $problem->status);
        $this->assertSame('Unauthorized', $problem->title);
        $this->assertSame('Unauthorized', $problem->detail);
    }

    #[Test]
    public function factory_unauthorized_with_custom_detail(): void
    {
        $problem = ProblemDetails::unauthorized('Token expired');

        $this->assertSame(401, $problem->status);
        $this->assertSame('Token expired', $problem->detail);
    }

    #[Test]
    public function factory_forbidden(): void
    {
        $problem = ProblemDetails::forbidden();

        $this->assertSame(403, $problem->status);
        $this->assertSame('Forbidden', $problem->title);
        $this->assertSame('Forbidden', $problem->detail);
    }

    #[Test]
    public function factory_not_found(): void
    {
        $problem = ProblemDetails::notFound();

        $this->assertSame(404, $problem->status);
        $this->assertSame('Not Found', $problem->title);
        $this->assertSame('Not Found', $problem->detail);
    }

    #[Test]
    public function factory_conflict(): void
    {
        $problem = ProblemDetails::conflict('Resource already exists');

        $this->assertSame(409, $problem->status);
        $this->assertSame('Conflict', $problem->title);
        $this->assertSame('Resource already exists', $problem->detail);
    }

    #[Test]
    public function factory_unprocessable_entity(): void
    {
        $problem = ProblemDetails::unprocessableEntity('Validation failed', ['email' => 'Invalid']);

        $this->assertSame(422, $problem->status);
        $this->assertSame('Unprocessable Entity', $problem->title);
        $this->assertSame('Validation failed', $problem->detail);
        $this->assertSame(['email' => 'Invalid'], $problem->extensions['field_errors']);
    }

    #[Test]
    public function factory_internal_error(): void
    {
        $problem = ProblemDetails::internalError();

        $this->assertSame(500, $problem->status);
        $this->assertSame('Internal Server Error', $problem->title);
        $this->assertSame('Internal Server Error', $problem->detail);
    }

    #[Test]
    public function factory_too_many_requests(): void
    {
        $problem = ProblemDetails::tooManyRequests('Rate limit exceeded', 60);

        $this->assertSame(429, $problem->status);
        $this->assertSame('Too Many Requests', $problem->title);
        $this->assertSame('Rate limit exceeded', $problem->detail);
        $this->assertSame(60, $problem->extensions['retryAfter']);
    }

    #[Test]
    public function factory_too_many_requests_without_retry_after(): void
    {
        $problem = ProblemDetails::tooManyRequests();

        $this->assertSame(429, $problem->status);
        $this->assertArrayNotHasKey('retryAfter', $problem->extensions);
    }

    #[Test]
    public function extensions_are_flattened_into_json(): void
    {
        $problem = new ProblemDetails(
            status: 400,
            title: 'Bad Request',
            extensions: [
                'balance' => 30,
                'accounts' => ['/account/12345', '/account/67890'],
            ],
        );

        $json = $problem->jsonSerialize();

        $this->assertSame(30, $json['balance']);
        $this->assertSame(['/account/12345', '/account/67890'], $json['accounts']);
    }
}
