---
outline: deep
---

# HTTP Client

LatticePHP provides an HTTP client for making outbound requests to external APIs through the `lattice/http-client` package.

## Making Requests

```php
use Lattice\HttpClient\HttpClient;

$client = new HttpClient();

// GET request
$response = $client->get('https://api.example.com/users');
$users = $response->json();

// POST with JSON body
$response = $client->post('https://api.example.com/users', [
    'name' => 'Alice',
    'email' => 'alice@example.com',
]);

// With headers
$response = $client->withHeaders([
    'Authorization' => 'Bearer ' . $apiKey,
    'Accept' => 'application/json',
])->get('https://api.example.com/data');

// With timeout
$response = $client->withTimeout(10)->get('https://slow-api.example.com/data');
```

## Error Handling

```php
use Lattice\HttpClient\HttpClientException;

try {
    $response = $client->get('https://api.example.com/data');
} catch (HttpClientException $e) {
    // Connection failure, timeout, etc.
    Log::error('API call failed', ['error' => $e->getMessage()]);
}
```

## Testing

Use `FakeHttpClient` to mock external API calls:

```php
use Lattice\HttpClient\FakeHttpClient;

$fake = new FakeHttpClient();

// Register a fake response
$fake->fake('https://api.example.com/users', [
    'status' => 200,
    'body' => [
        ['id' => 1, 'name' => 'Alice'],
        ['id' => 2, 'name' => 'Bob'],
    ],
]);

// Inject the fake
$container->instance(HttpClient::class, $fake);

// Code under test makes the request and gets the fake response
$response = $client->get('https://api.example.com/users');
$this->assertCount(2, $response->json());

// Assert the request was made
$fake->assertSent('https://api.example.com/users');
```

::: tip
Use `FakeHttpClient` in integration tests to avoid real network calls. Combine with the [Circuit Breaker](circuit-breaker.md) for resilient external API communication.
:::

## Next Steps

- [Circuit Breaker](circuit-breaker.md) -- protect against external service failures
- [Testing](testing.md) -- faking external dependencies
- [Observability](observability.md) -- logging external API calls
