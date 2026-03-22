<?php

declare(strict_types=1);

namespace Lattice\HttpClient\Tests\Unit;

use Lattice\HttpClient\HttpClientResponse;
use Lattice\HttpClient\HttpClientException;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HttpClientResponseTest extends TestCase
{
    #[Test]
    public function test_status_returns_status_code(): void
    {
        $response = new HttpClientResponse(200, [], '');

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function test_body_returns_raw_body(): void
    {
        $response = new HttpClientResponse(200, [], 'hello');

        $this->assertEquals('hello', $response->body());
    }

    #[Test]
    public function test_json_returns_decoded_body(): void
    {
        $response = new HttpClientResponse(200, [], '{"key":"value"}');

        $this->assertEquals(['key' => 'value'], $response->json());
    }

    #[Test]
    public function test_headers_returns_response_headers(): void
    {
        $headers = ['Content-Type' => 'application/json'];
        $response = new HttpClientResponse(200, $headers, '');

        $this->assertEquals($headers, $response->headers());
    }

    #[Test]
    public function test_ok_returns_true_for_2xx(): void
    {
        $this->assertTrue((new HttpClientResponse(200, [], ''))->ok());
        $this->assertTrue((new HttpClientResponse(201, [], ''))->ok());
        $this->assertTrue((new HttpClientResponse(299, [], ''))->ok());
    }

    #[Test]
    public function test_ok_returns_false_for_non_2xx(): void
    {
        $this->assertFalse((new HttpClientResponse(301, [], ''))->ok());
        $this->assertFalse((new HttpClientResponse(404, [], ''))->ok());
        $this->assertFalse((new HttpClientResponse(500, [], ''))->ok());
    }

    #[Test]
    public function test_successful_is_alias_for_ok(): void
    {
        $this->assertTrue((new HttpClientResponse(200, [], ''))->successful());
        $this->assertFalse((new HttpClientResponse(500, [], ''))->successful());
    }

    #[Test]
    public function test_failed_returns_true_for_4xx_and_5xx(): void
    {
        $this->assertTrue((new HttpClientResponse(400, [], ''))->failed());
        $this->assertTrue((new HttpClientResponse(404, [], ''))->failed());
        $this->assertTrue((new HttpClientResponse(500, [], ''))->failed());
        $this->assertTrue((new HttpClientResponse(503, [], ''))->failed());
    }

    #[Test]
    public function test_failed_returns_false_for_2xx(): void
    {
        $this->assertFalse((new HttpClientResponse(200, [], ''))->failed());
        $this->assertFalse((new HttpClientResponse(201, [], ''))->failed());
    }

    #[Test]
    public function test_throw_throws_on_failure(): void
    {
        $response = new HttpClientResponse(500, [], 'Server Error');

        $this->expectException(HttpClientException::class);
        $response->throw();
    }

    #[Test]
    public function test_throw_returns_self_on_success(): void
    {
        $response = new HttpClientResponse(200, [], 'ok');

        $this->assertSame($response, $response->throw());
    }
}
