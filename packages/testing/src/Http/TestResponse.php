<?php

declare(strict_types=1);

namespace Lattice\Testing\Http;

use PHPUnit\Framework\Assert;

final class TestResponse
{
    /**
     * @param int $status HTTP status code
     * @param array<string, string> $headers Response headers
     * @param mixed $body Response body
     */
    public function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly mixed $body,
    ) {}

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getBody(): mixed
    {
        return $this->body;
    }

    /**
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    // --- Status Code Assertions ---

    public function assertStatus(int $status): self
    {
        Assert::assertSame(
            $status,
            $this->status,
            sprintf('Expected status code %d but received %d.', $status, $this->status)
        );

        return $this;
    }

    public function assertOk(): self
    {
        return $this->assertStatus(200);
    }

    public function assertCreated(): self
    {
        return $this->assertStatus(201);
    }

    public function assertNoContent(): self
    {
        return $this->assertStatus(204);
    }

    public function assertNotFound(): self
    {
        return $this->assertStatus(404);
    }

    public function assertUnauthorized(): self
    {
        return $this->assertStatus(401);
    }

    public function assertForbidden(): self
    {
        return $this->assertStatus(403);
    }

    public function assertUnprocessable(): self
    {
        return $this->assertStatus(422);
    }

    public function assertSuccessful(): self
    {
        Assert::assertTrue(
            $this->status >= 200 && $this->status < 300,
            sprintf('Expected a successful status code (2xx) but received %d.', $this->status)
        );

        return $this;
    }

    public function assertServerError(): self
    {
        Assert::assertTrue(
            $this->status >= 500 && $this->status < 600,
            sprintf('Expected a server error status code (5xx) but received %d.', $this->status)
        );

        return $this;
    }

    // --- JSON Body Assertions ---

    /**
     * Assert that the response body contains the expected key-value pairs.
     *
     * @param array<string, mixed> $expected
     */
    public function assertJson(array $expected): self
    {
        Assert::assertIsArray($this->body, 'Response body is not an array.');

        foreach ($expected as $key => $value) {
            Assert::assertArrayHasKey($key, $this->body, sprintf('Response body missing key "%s".', $key));
            Assert::assertSame(
                $value,
                $this->body[$key],
                sprintf('Response body key "%s" does not match expected value.', $key)
            );
        }

        return $this;
    }

    /**
     * Assert a value at a dot-notation path in the JSON body.
     */
    public function assertJsonPath(string $path, mixed $expected): self
    {
        $segments = explode('.', $path);
        $current = $this->body;

        foreach ($segments as $segment) {
            Assert::assertIsArray($current, sprintf('Cannot traverse path "%s": non-array encountered.', $path));
            Assert::assertArrayHasKey($segment, $current, sprintf('Path "%s" not found in response body.', $path));
            $current = $current[$segment];
        }

        Assert::assertSame(
            $expected,
            $current,
            sprintf('Value at path "%s" does not match expected value.', $path)
        );

        return $this;
    }

    /**
     * Assert that the JSON body has the given structure (keys exist).
     *
     * @param list<string> $keys
     */
    public function assertJsonStructure(array $keys, ?array $data = null): self
    {
        $data ??= $this->body;

        Assert::assertIsArray($data, 'Response body is not an array.');

        foreach ($keys as $key => $value) {
            if (is_array($value)) {
                // Nested structure check
                Assert::assertArrayHasKey($key, $data, sprintf('Response body missing key "%s".', $key));
                $this->assertJsonStructure($value, $data[$key]);
            } else {
                Assert::assertArrayHasKey($value, $data, sprintf('Response body missing key "%s".', $value));
            }
        }

        return $this;
    }

    /**
     * Assert that the JSON body contains validation errors for the given fields.
     *
     * Supports common error response formats:
     * - { "errors": { "field": [...] } }
     * - { "errors": [ { "field": "name", ... } ] }
     *
     * @param list<string> $fields
     */
    public function assertJsonValidationErrors(array $fields): self
    {
        Assert::assertIsArray($this->body, 'Response body is not an array.');
        Assert::assertArrayHasKey('errors', $this->body, 'Response body missing "errors" key.');

        $errors = $this->body['errors'];

        foreach ($fields as $field) {
            if (is_array($errors) && !array_is_list($errors)) {
                // Keyed errors: { "field": ["message1", "message2"] }
                Assert::assertArrayHasKey(
                    $field,
                    $errors,
                    sprintf('No validation error found for field "%s".', $field)
                );
            } else {
                // List of error objects
                $found = false;
                foreach ($errors as $error) {
                    if (is_array($error) && ($error['field'] ?? null) === $field) {
                        $found = true;
                        break;
                    }
                }
                Assert::assertTrue($found, sprintf('No validation error found for field "%s".', $field));
            }
        }

        return $this;
    }

    /**
     * Assert that the JSON body does NOT contain validation errors for the given fields.
     *
     * @param list<string> $fields
     */
    public function assertJsonMissingValidationErrors(array $fields): self
    {
        Assert::assertIsArray($this->body, 'Response body is not an array.');

        // If no errors key at all, that's fine — no validation errors exist
        if (!array_key_exists('errors', $this->body)) {
            return $this;
        }

        $errors = $this->body['errors'];

        foreach ($fields as $field) {
            if (is_array($errors) && !array_is_list($errors)) {
                Assert::assertArrayNotHasKey(
                    $field,
                    $errors,
                    sprintf('Unexpected validation error found for field "%s".', $field)
                );
            } else {
                foreach ($errors as $error) {
                    if (is_array($error) && ($error['field'] ?? null) === $field) {
                        Assert::fail(sprintf('Unexpected validation error found for field "%s".', $field));
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Assert the count of items in the JSON body, optionally at a specific key.
     */
    public function assertJsonCount(int $count, ?string $key = null): self
    {
        $data = $this->body;

        if ($key !== null) {
            Assert::assertIsArray($data, 'Response body is not an array.');
            Assert::assertArrayHasKey($key, $data, sprintf('Response body missing key "%s".', $key));
            $data = $data[$key];
        }

        Assert::assertIsArray($data, 'Target data is not an array.');
        Assert::assertCount(
            $count,
            $data,
            sprintf('Expected JSON count of %d but found %d.', $count, count($data))
        );

        return $this;
    }

    /**
     * Assert that the response body exactly matches the given data.
     *
     * @param array<string, mixed> $expected
     */
    public function assertExactJson(array $expected): self
    {
        Assert::assertSame($expected, $this->body, 'Response body does not exactly match expected JSON.');

        return $this;
    }

    /**
     * Assert that a key is missing from the JSON body.
     */
    public function assertJsonMissing(array $data): self
    {
        Assert::assertIsArray($this->body, 'Response body is not an array.');

        foreach ($data as $key => $value) {
            if (is_int($key)) {
                // Value should not exist anywhere in the body values
                Assert::assertNotContains($value, $this->body, sprintf('Found unexpected value in response body.'));
            } else {
                // Key-value pair should not exist
                if (array_key_exists($key, $this->body)) {
                    Assert::assertNotSame(
                        $value,
                        $this->body[$key],
                        sprintf('Found unexpected key-value pair "%s" in response body.', $key)
                    );
                }
            }
        }

        return $this;
    }

    // --- Header Assertions ---

    /**
     * Assert that a response header exists, optionally with a specific value.
     */
    public function assertHeader(string $name, ?string $value = null): self
    {
        // Case-insensitive header lookup
        $found = false;
        $actualValue = null;

        foreach ($this->headers as $headerName => $headerValue) {
            if (strcasecmp($headerName, $name) === 0) {
                $found = true;
                $actualValue = $headerValue;
                break;
            }
        }

        Assert::assertTrue($found, sprintf('Header "%s" not found in response.', $name));

        if ($value !== null) {
            Assert::assertSame(
                $value,
                $actualValue,
                sprintf('Header "%s" expected value "%s" but got "%s".', $name, $value, (string) $actualValue)
            );
        }

        return $this;
    }

    /**
     * Assert that a response header is missing.
     */
    public function assertHeaderMissing(string $name): self
    {
        foreach ($this->headers as $headerName => $headerValue) {
            if (strcasecmp($headerName, $name) === 0) {
                Assert::fail(sprintf('Unexpected header "%s" found in response.', $name));
            }
        }

        return $this;
    }
}
