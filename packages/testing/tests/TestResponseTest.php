<?php

declare(strict_types=1);

namespace Lattice\Testing\Tests;

use Lattice\Testing\Http\TestResponse;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TestResponseTest extends TestCase
{
    #[Test]
    public function it_asserts_status_code(): void
    {
        $response = new TestResponse(200, [], null);

        $result = $response->assertStatus(200);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_fails_on_wrong_status_code(): void
    {
        $response = new TestResponse(404, [], null);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $response->assertStatus(200);
    }

    #[Test]
    public function it_asserts_json_body(): void
    {
        $response = new TestResponse(200, [], ['name' => 'John', 'age' => 30]);

        $result = $response->assertJson(['name' => 'John']);

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_fails_on_missing_json_key(): void
    {
        $response = new TestResponse(200, [], ['name' => 'John']);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $response->assertJson(['email' => 'john@example.com']);
    }

    #[Test]
    public function it_asserts_json_path(): void
    {
        $response = new TestResponse(200, [], [
            'data' => [
                'user' => [
                    'name' => 'John',
                ],
            ],
        ]);

        $result = $response->assertJsonPath('data.user.name', 'John');

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_fails_on_wrong_json_path_value(): void
    {
        $response = new TestResponse(200, [], [
            'data' => ['name' => 'John'],
        ]);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $response->assertJsonPath('data.name', 'Jane');
    }

    #[Test]
    public function it_asserts_header_exists(): void
    {
        $response = new TestResponse(200, ['Content-Type' => 'application/json'], null);

        $result = $response->assertHeader('Content-Type');

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_asserts_header_value(): void
    {
        $response = new TestResponse(200, ['Content-Type' => 'application/json'], null);

        $result = $response->assertHeader('Content-Type', 'application/json');

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_fails_on_missing_header(): void
    {
        $response = new TestResponse(200, [], null);

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);

        $response->assertHeader('X-Missing');
    }

    #[Test]
    public function it_asserts_not_found(): void
    {
        $response = new TestResponse(404, [], null);

        $result = $response->assertNotFound();

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_asserts_forbidden(): void
    {
        $response = new TestResponse(403, [], null);

        $result = $response->assertForbidden();

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_asserts_unauthorized(): void
    {
        $response = new TestResponse(401, [], null);

        $result = $response->assertUnauthorized();

        $this->assertSame($response, $result);
    }

    #[Test]
    public function it_returns_body(): void
    {
        $response = new TestResponse(200, [], ['key' => 'value']);

        $this->assertSame(['key' => 'value'], $response->getBody());
    }

    #[Test]
    public function it_returns_status(): void
    {
        $response = new TestResponse(201, [], null);

        $this->assertSame(201, $response->getStatus());
    }

    #[Test]
    public function it_chains_assertions(): void
    {
        $response = new TestResponse(200, ['Content-Type' => 'application/json'], ['id' => 1, 'name' => 'John']);

        $response
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJson(['id' => 1])
            ->assertJsonPath('name', 'John');

        // If we get here, all assertions passed
        $this->assertTrue(true);
    }
}
