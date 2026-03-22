<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit;

use Lattice\Database\Pagination\Paginator;
use Lattice\Http\Resource;
use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ResponseFactoryTest extends TestCase
{
    #[Test]
    public function test_json_creates_proper_response(): void
    {
        $response = ResponseFactory::json(['key' => 'value'], 200, ['X-Custom' => 'header']);

        $this->assertInstanceOf(Response::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['key' => 'value'], $response->getBody());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
        $this->assertSame('header', $response->getHeaders()['X-Custom']);
    }

    #[Test]
    public function test_json_defaults_to_200(): void
    {
        $response = ResponseFactory::json(['ok' => true]);

        $this->assertSame(200, $response->getStatusCode());
    }

    #[Test]
    public function test_created_returns_201(): void
    {
        $response = ResponseFactory::created(['id' => 42]);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame(['id' => 42], $response->getBody());
        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
    }

    #[Test]
    public function test_created_with_custom_headers(): void
    {
        $response = ResponseFactory::created(['id' => 1], ['Location' => '/items/1']);

        $this->assertSame(201, $response->getStatusCode());
        $this->assertSame('/items/1', $response->getHeaders()['Location']);
    }

    #[Test]
    public function test_accepted_returns_202(): void
    {
        $response = ResponseFactory::accepted(['job_id' => 'abc']);

        $this->assertSame(202, $response->getStatusCode());
        $this->assertSame(['job_id' => 'abc'], $response->getBody());
    }

    #[Test]
    public function test_no_content_returns_204(): void
    {
        $response = ResponseFactory::noContent();

        $this->assertSame(204, $response->getStatusCode());
        $this->assertNull($response->getBody());
        $this->assertEmpty($response->getHeaders());
    }

    #[Test]
    public function test_error_returns_problem_details_format(): void
    {
        $response = ResponseFactory::error('Something went wrong', 500);

        $this->assertSame(500, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertSame('https://httpstatuses.io/500', $body['type']);
        $this->assertSame('Internal Server Error', $body['title']);
        $this->assertSame(500, $body['status']);
        $this->assertSame('Something went wrong', $body['detail']);
        $this->assertArrayNotHasKey('errors', $body);
    }

    #[Test]
    public function test_error_includes_errors_when_provided(): void
    {
        $errors = ['field' => ['required']];
        $response = ResponseFactory::error('Validation failed', 422, $errors);

        $body = $response->getBody();
        $this->assertSame($errors, $body['errors']);
    }

    #[Test]
    public function test_error_with_different_status_codes(): void
    {
        $response400 = ResponseFactory::error('Bad input', 400);
        $this->assertSame('Bad Request', $response400->getBody()['title']);

        $response404 = ResponseFactory::error('Not found', 404);
        $this->assertSame('Not Found', $response404->getBody()['title']);

        $response403 = ResponseFactory::error('Forbidden', 403);
        $this->assertSame('Forbidden', $response403->getBody()['title']);
    }

    #[Test]
    public function test_validation_error_returns_422_with_field_errors(): void
    {
        $errors = [
            'email' => ['The email field is required.'],
            'name' => ['The name field must be a string.'],
        ];

        $response = ResponseFactory::validationError($errors);

        $this->assertSame(422, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertSame('https://httpstatuses.io/422', $body['type']);
        $this->assertSame('Unprocessable Entity', $body['title']);
        $this->assertSame(422, $body['status']);
        $this->assertSame('The given data was invalid.', $body['detail']);
        $this->assertSame($errors, $body['errors']);
    }

    #[Test]
    public function test_paginated_with_lattice_paginator(): void
    {
        $paginator = new Paginator(
            items: [['id' => 1], ['id' => 2]],
            total: 10,
            perPage: 2,
            currentPage: 1,
        );

        $response = ResponseFactory::paginated($paginator);

        $this->assertSame(200, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertSame([['id' => 1], ['id' => 2]], $body['data']);
        $this->assertSame(10, $body['meta']['total']);
        $this->assertSame(2, $body['meta']['per_page']);
        $this->assertSame(1, $body['meta']['current_page']);
        $this->assertSame(5, $body['meta']['last_page']);
        $this->assertTrue($body['meta']['has_more']);
    }

    #[Test]
    public function test_paginated_last_page_has_more_false(): void
    {
        $paginator = new Paginator(
            items: [['id' => 9], ['id' => 10]],
            total: 10,
            perPage: 2,
            currentPage: 5,
        );

        $response = ResponseFactory::paginated($paginator);
        $body = $response->getBody();

        $this->assertFalse($body['meta']['has_more']);
        $this->assertSame(5, $body['meta']['last_page']);
    }

    #[Test]
    public function test_paginated_with_resource_class(): void
    {
        $paginator = new Paginator(
            items: [
                (object) ['id' => 1, 'name' => 'Alice'],
                (object) ['id' => 2, 'name' => 'Bob'],
            ],
            total: 2,
            perPage: 10,
            currentPage: 1,
        );

        $response = ResponseFactory::paginated($paginator, StubResource::class);

        $body = $response->getBody();
        $this->assertSame([
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ], $body['data']);
    }

    #[Test]
    public function test_paginated_fallback_for_unknown_type(): void
    {
        $data = [['id' => 1], ['id' => 2]];

        $response = ResponseFactory::paginated($data);

        $body = $response->getBody();
        $this->assertSame($data, $body['data']);
    }

    #[Test]
    public function test_with_rate_limit_headers(): void
    {
        $response = ResponseFactory::json(['ok' => true]);
        $result = ResponseFactory::withRateLimitHeaders($response, 100, 95, 1700000000);

        $headers = $result->getHeaders();
        $this->assertSame('100', $headers['X-RateLimit-Limit']);
        $this->assertSame('95', $headers['X-RateLimit-Remaining']);
        $this->assertSame('1700000000', $headers['X-RateLimit-Reset']);
        $this->assertArrayNotHasKey('Retry-After', $headers);
    }

    #[Test]
    public function test_with_rate_limit_headers_exceeded(): void
    {
        $response = ResponseFactory::json(['error' => 'rate limited'], 429);
        $resetTime = time() + 60;
        $result = ResponseFactory::withRateLimitHeaders($response, 100, 0, $resetTime, exceeded: true);

        $headers = $result->getHeaders();
        $this->assertSame('100', $headers['X-RateLimit-Limit']);
        $this->assertSame('0', $headers['X-RateLimit-Remaining']);
        $this->assertArrayHasKey('Retry-After', $headers);
        $this->assertGreaterThan(0, (int) $headers['Retry-After']);
    }

    #[Test]
    public function test_too_many_requests(): void
    {
        $resetTime = time() + 120;
        $response = ResponseFactory::tooManyRequests(60, $resetTime);

        $this->assertSame(429, $response->getStatusCode());

        $body = $response->getBody();
        $this->assertSame('Too Many Requests', $body['title']);
        $this->assertSame(429, $body['status']);

        $headers = $response->getHeaders();
        $this->assertSame('60', $headers['X-RateLimit-Limit']);
        $this->assertSame('0', $headers['X-RateLimit-Remaining']);
        $this->assertArrayHasKey('Retry-After', $headers);
    }

    #[Test]
    public function test_remaining_cannot_be_negative(): void
    {
        $response = ResponseFactory::json([]);
        $result = ResponseFactory::withRateLimitHeaders($response, 10, -5, time() + 60);

        $this->assertSame('0', $result->getHeaders()['X-RateLimit-Remaining']);
    }
}

/**
 * Stub resource for testing resource wrapping in paginated responses.
 */
final class StubResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }
}
