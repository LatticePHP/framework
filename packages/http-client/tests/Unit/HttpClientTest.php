<?php

declare(strict_types=1);

namespace Lattice\HttpClient\Tests\Unit;

use Lattice\HttpClient\HttpClient;
use Lattice\HttpClient\HttpClientResponse;
use Lattice\HttpClient\Testing\FakeHttpClient;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class HttpClientTest extends TestCase
{
    private FakeHttpClient $fake;

    protected function setUp(): void
    {
        $this->fake = new FakeHttpClient();
    }

    #[Test]
    public function test_get_request(): void
    {
        $this->fake->stub('https://api.example.com/users', new HttpClientResponse(
            200,
            ['Content-Type' => 'application/json'],
            '{"users":[]}',
        ));

        $response = $this->fake->get('https://api.example.com/users');

        $this->assertEquals(200, $response->status());
        $this->assertEquals(['users' => []], $response->json());
    }

    #[Test]
    public function test_post_request(): void
    {
        $this->fake->stub('https://api.example.com/users', new HttpClientResponse(
            201,
            [],
            '{"id":1}',
        ));

        $response = $this->fake->post('https://api.example.com/users', ['name' => 'John']);

        $this->assertEquals(201, $response->status());
    }

    #[Test]
    public function test_put_request(): void
    {
        $this->fake->stub('https://api.example.com/users/1', new HttpClientResponse(200, [], '{}'));

        $response = $this->fake->put('https://api.example.com/users/1', ['name' => 'Jane']);

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function test_patch_request(): void
    {
        $this->fake->stub('https://api.example.com/users/1', new HttpClientResponse(200, [], '{}'));

        $response = $this->fake->patch('https://api.example.com/users/1', ['name' => 'Jane']);

        $this->assertEquals(200, $response->status());
    }

    #[Test]
    public function test_delete_request(): void
    {
        $this->fake->stub('https://api.example.com/users/1', new HttpClientResponse(204, [], ''));

        $response = $this->fake->delete('https://api.example.com/users/1');

        $this->assertEquals(204, $response->status());
    }

    #[Test]
    public function test_assert_sent(): void
    {
        $this->fake->stub('https://api.example.com/data', new HttpClientResponse(200, [], ''));
        $this->fake->get('https://api.example.com/data');

        // Should not throw
        $this->fake->assertSent('https://api.example.com/data');
        $this->assertTrue(true);
    }

    #[Test]
    public function test_assert_not_sent(): void
    {
        // Should not throw
        $this->fake->assertNotSent('https://api.example.com/never-called');
        $this->assertTrue(true);
    }

    #[Test]
    public function test_assert_sent_fails_when_not_sent(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->fake->assertSent('https://api.example.com/never-called');
    }

    #[Test]
    public function test_assert_not_sent_fails_when_sent(): void
    {
        $this->fake->stub('https://api.example.com/data', new HttpClientResponse(200, [], ''));
        $this->fake->get('https://api.example.com/data');

        $this->expectException(\RuntimeException::class);
        $this->fake->assertNotSent('https://api.example.com/data');
    }

    #[Test]
    public function test_unstubbed_url_returns_404(): void
    {
        $response = $this->fake->get('https://unknown.com');

        $this->assertEquals(404, $response->status());
    }

    #[Test]
    public function test_with_headers_returns_new_instance(): void
    {
        $client = new HttpClient();
        $withHeaders = $client->withHeaders(['X-Custom' => 'value']);

        $this->assertNotSame($client, $withHeaders);
    }

    #[Test]
    public function test_with_auth_returns_new_instance(): void
    {
        $client = new HttpClient();
        $withAuth = $client->withAuth('token123');

        $this->assertNotSame($client, $withAuth);
    }

    #[Test]
    public function test_with_timeout_returns_new_instance(): void
    {
        $client = new HttpClient();
        $withTimeout = $client->withTimeout(30);

        $this->assertNotSame($client, $withTimeout);
    }
}
