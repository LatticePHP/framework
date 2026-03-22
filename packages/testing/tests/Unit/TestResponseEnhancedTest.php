<?php

declare(strict_types=1);

namespace Lattice\Testing\Tests\Unit;

use Lattice\Testing\Http\TestResponse;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class TestResponseEnhancedTest extends TestCase
{
    // --- Status Code Assertions ---

    #[Test]
    public function test_assert_ok(): void
    {
        $response = new TestResponse(200, [], null);
        $this->assertSame($response, $response->assertOk());
    }

    #[Test]
    public function test_assert_ok_fails_on_non_200(): void
    {
        $response = new TestResponse(201, [], null);
        $this->expectException(AssertionFailedError::class);
        $response->assertOk();
    }

    #[Test]
    public function test_assert_created(): void
    {
        $response = new TestResponse(201, [], null);
        $this->assertSame($response, $response->assertCreated());
    }

    #[Test]
    public function test_assert_created_fails_on_non_201(): void
    {
        $response = new TestResponse(200, [], null);
        $this->expectException(AssertionFailedError::class);
        $response->assertCreated();
    }

    #[Test]
    public function test_assert_no_content(): void
    {
        $response = new TestResponse(204, [], null);
        $this->assertSame($response, $response->assertNoContent());
    }

    #[Test]
    public function test_assert_no_content_fails_on_non_204(): void
    {
        $response = new TestResponse(200, [], null);
        $this->expectException(AssertionFailedError::class);
        $response->assertNoContent();
    }

    #[Test]
    public function test_assert_unprocessable(): void
    {
        $response = new TestResponse(422, [], null);
        $this->assertSame($response, $response->assertUnprocessable());
    }

    #[Test]
    public function test_assert_unprocessable_fails_on_non_422(): void
    {
        $response = new TestResponse(400, [], null);
        $this->expectException(AssertionFailedError::class);
        $response->assertUnprocessable();
    }

    #[Test]
    public function test_assert_successful(): void
    {
        foreach ([200, 201, 204, 299] as $status) {
            $response = new TestResponse($status, [], null);
            $this->assertSame($response, $response->assertSuccessful());
        }
    }

    #[Test]
    public function test_assert_successful_fails_on_non_2xx(): void
    {
        $response = new TestResponse(400, [], null);
        $this->expectException(AssertionFailedError::class);
        $response->assertSuccessful();
    }

    #[Test]
    public function test_assert_server_error(): void
    {
        $response = new TestResponse(500, [], null);
        $this->assertSame($response, $response->assertServerError());
    }

    #[Test]
    public function test_assert_server_error_fails_on_non_5xx(): void
    {
        $response = new TestResponse(400, [], null);
        $this->expectException(AssertionFailedError::class);
        $response->assertServerError();
    }

    // --- JSON Validation Error Assertions ---

    #[Test]
    public function test_assert_json_validation_errors_with_keyed_format(): void
    {
        $response = new TestResponse(422, [], [
            'errors' => [
                'name' => ['Name is required.'],
                'email' => ['Email is invalid.'],
            ],
        ]);

        $this->assertSame($response, $response->assertJsonValidationErrors(['name', 'email']));
    }

    #[Test]
    public function test_assert_json_validation_errors_fails_on_missing_field(): void
    {
        $response = new TestResponse(422, [], [
            'errors' => [
                'name' => ['Name is required.'],
            ],
        ]);

        $this->expectException(AssertionFailedError::class);
        $response->assertJsonValidationErrors(['name', 'email']);
    }

    #[Test]
    public function test_assert_json_validation_errors_with_list_format(): void
    {
        $response = new TestResponse(422, [], [
            'errors' => [
                ['field' => 'name', 'message' => 'Name is required.'],
                ['field' => 'email', 'message' => 'Email is invalid.'],
            ],
        ]);

        $this->assertSame($response, $response->assertJsonValidationErrors(['name', 'email']));
    }

    #[Test]
    public function test_assert_json_missing_validation_errors_passes(): void
    {
        $response = new TestResponse(200, [], [
            'errors' => [
                'name' => ['Name is required.'],
            ],
        ]);

        $this->assertSame($response, $response->assertJsonMissingValidationErrors(['email']));
    }

    #[Test]
    public function test_assert_json_missing_validation_errors_passes_when_no_errors_key(): void
    {
        $response = new TestResponse(200, [], ['data' => 'ok']);

        $this->assertSame($response, $response->assertJsonMissingValidationErrors(['name']));
    }

    #[Test]
    public function test_assert_json_missing_validation_errors_fails_when_field_has_error(): void
    {
        $response = new TestResponse(422, [], [
            'errors' => [
                'name' => ['Name is required.'],
            ],
        ]);

        $this->expectException(AssertionFailedError::class);
        $response->assertJsonMissingValidationErrors(['name']);
    }

    // --- JSON Count Assertions ---

    #[Test]
    public function test_assert_json_count_on_root(): void
    {
        $response = new TestResponse(200, [], ['a', 'b', 'c']);
        $this->assertSame($response, $response->assertJsonCount(3));
    }

    #[Test]
    public function test_assert_json_count_on_key(): void
    {
        $response = new TestResponse(200, [], [
            'data' => [1, 2, 3, 4],
        ]);
        $this->assertSame($response, $response->assertJsonCount(4, 'data'));
    }

    #[Test]
    public function test_assert_json_count_fails_on_wrong_count(): void
    {
        $response = new TestResponse(200, [], ['a', 'b']);
        $this->expectException(AssertionFailedError::class);
        $response->assertJsonCount(5);
    }

    // --- JSON Structure Assertions ---

    #[Test]
    public function test_assert_json_structure(): void
    {
        $response = new TestResponse(200, [], [
            'id' => 1,
            'name' => 'John',
            'meta' => ['created_at' => '2024-01-01'],
        ]);

        $this->assertSame($response, $response->assertJsonStructure([
            'id',
            'name',
            'meta' => ['created_at'],
        ]));
    }

    #[Test]
    public function test_assert_json_structure_fails_on_missing_key(): void
    {
        $response = new TestResponse(200, [], ['id' => 1]);
        $this->expectException(AssertionFailedError::class);
        $response->assertJsonStructure(['id', 'name']);
    }

    // --- Exact JSON Assertions ---

    #[Test]
    public function test_assert_exact_json(): void
    {
        $data = ['id' => 1, 'name' => 'John'];
        $response = new TestResponse(200, [], $data);
        $this->assertSame($response, $response->assertExactJson($data));
    }

    #[Test]
    public function test_assert_exact_json_fails_on_extra_key(): void
    {
        $response = new TestResponse(200, [], ['id' => 1, 'name' => 'John']);
        $this->expectException(AssertionFailedError::class);
        $response->assertExactJson(['id' => 1]);
    }

    // --- JSON Missing Assertions ---

    #[Test]
    public function test_assert_json_missing(): void
    {
        $response = new TestResponse(200, [], ['id' => 1, 'name' => 'John']);
        $this->assertSame($response, $response->assertJsonMissing(['email' => 'john@test.com']));
    }

    #[Test]
    public function test_assert_json_missing_fails_when_value_matches(): void
    {
        $response = new TestResponse(200, [], ['id' => 1, 'name' => 'John']);
        $this->expectException(AssertionFailedError::class);
        $response->assertJsonMissing(['name' => 'John']);
    }

    // --- Header Assertions ---

    #[Test]
    public function test_assert_header_missing(): void
    {
        $response = new TestResponse(200, ['Content-Type' => 'application/json'], null);
        $this->assertSame($response, $response->assertHeaderMissing('X-Custom'));
    }

    #[Test]
    public function test_assert_header_missing_fails_when_present(): void
    {
        $response = new TestResponse(200, ['X-Custom' => 'value'], null);
        $this->expectException(AssertionFailedError::class);
        $response->assertHeaderMissing('X-Custom');
    }

    #[Test]
    public function test_assert_header_case_insensitive(): void
    {
        $response = new TestResponse(200, ['Content-Type' => 'application/json'], null);
        $this->assertSame($response, $response->assertHeader('content-type', 'application/json'));
    }

    // --- Chaining ---

    #[Test]
    public function test_full_assertion_chain(): void
    {
        $response = new TestResponse(200, ['Content-Type' => 'application/json'], [
            'data' => [
                ['id' => 1, 'name' => 'Alice'],
                ['id' => 2, 'name' => 'Bob'],
            ],
        ]);

        $response
            ->assertOk()
            ->assertSuccessful()
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'Alice')
            ->assertJsonStructure(['data']);

        $this->assertTrue(true);
    }
}
