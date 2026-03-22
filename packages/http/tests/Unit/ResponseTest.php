<?php

declare(strict_types=1);

namespace Lattice\Http\Tests\Unit;

use Lattice\Http\Response;
use PHPUnit\Framework\TestCase;

final class ResponseTest extends TestCase
{
    public function testJsonResponse(): void
    {
        $data = ['id' => 1, 'name' => 'John'];
        $response = Response::json($data);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame($data, $response->body);
        $this->assertSame('application/json', $response->headers['Content-Type']);
    }

    public function testJsonResponseWithCustomStatus(): void
    {
        $data = ['id' => 1];
        $response = Response::json($data, 201);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame($data, $response->body);
    }

    public function testNoContentResponse(): void
    {
        $response = Response::noContent();

        $this->assertSame(204, $response->statusCode);
        $this->assertNull($response->body);
    }

    public function testErrorResponse(): void
    {
        $response = Response::error('Not Found', 404);

        $this->assertSame(404, $response->statusCode);
        $this->assertSame(['error' => 'Not Found'], $response->body);
        $this->assertSame('application/json', $response->headers['Content-Type']);
    }

    public function testConstructor(): void
    {
        $response = new Response(
            statusCode: 202,
            headers: ['X-Custom' => 'value'],
            body: 'accepted',
        );

        $this->assertSame(202, $response->statusCode);
        $this->assertSame(['X-Custom' => 'value'], $response->headers);
        $this->assertSame('accepted', $response->body);
    }
}
