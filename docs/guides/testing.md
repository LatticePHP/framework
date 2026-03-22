# Testing

## Overview

LatticePHP provides a testing harness built on PHPUnit. The `Lattice\Testing\TestCase` base class gives you HTTP request helpers, database assertions, authentication shortcuts, and trait-based setup/teardown discovery. Fakes for events, queues, and workflows let you test side effects without real infrastructure.

## TestCase Base Class

Extend `Lattice\Testing\TestCase` for application-level tests. It bootstraps the application, discovers traits, and provides HTTP + database helpers:

```php
use Lattice\Testing\TestCase;
use Lattice\Testing\Traits\RefreshDatabase;

final class ContactApiTest extends TestCase
{
    use RefreshDatabase;

    protected function getModules(): array
    {
        return [ContactModule::class];
    }

    public function test_list_contacts(): void
    {
        $response = $this->getJson('/api/contacts');
        $response->assertOk();
    }
}
```

The base class calls `createApplication()` in `setUp()`, which boots a real `Application` with the modules you specify. Trait setUp/tearDown methods are discovered automatically by convention (`setUp{TraitBaseName}`, `tearDown{TraitBaseName}`).

## HTTP Request Helpers

All request methods return a `TestResponse` with fluent assertions:

```php
// GET request
$response = $this->getJson('/api/contacts');

// POST with body
$response = $this->postJson('/api/contacts', [
    'name' => 'Alice',
    'email' => 'alice@example.com',
]);

// PUT, PATCH, DELETE
$response = $this->putJson('/api/contacts/1', ['name' => 'Bob']);
$response = $this->patchJson('/api/contacts/1', ['status' => 'lead']);
$response = $this->deleteJson('/api/contacts/1');
```

All methods automatically set `Content-Type: application/json` and `Accept: application/json` headers.

### Authentication

Use `withToken()` to attach a Bearer token to all requests, or `actingAs()` to bypass auth entirely:

```php
// With a real JWT token
$this->withToken($accessToken)->getJson('/api/contacts');

// Bypass auth -- injects user directly into container
$this->actingAs($user)->getJson('/api/contacts');

// With workspace context
$this->actingAs($user, $workspace)->getJson('/api/contacts');
```

### Custom Headers

```php
$this->withHeaders(['X-Tenant-Id' => '42'])->getJson('/api/contacts');
$this->withHeader('Accept-Language', 'de')->getJson('/api/contacts');
```

## TestResponse Assertions

`Lattice\Testing\Http\TestResponse` provides chainable assertion methods:

### Status Code Assertions

```php
$response->assertOk();            // 200
$response->assertCreated();       // 201
$response->assertNoContent();     // 204
$response->assertNotFound();      // 404
$response->assertUnauthorized();  // 401
$response->assertForbidden();     // 403
$response->assertUnprocessable(); // 422
$response->assertSuccessful();    // 2xx
$response->assertServerError();   // 5xx
$response->assertStatus(418);     // Exact status
```

### JSON Body Assertions

```php
// Key-value matching
$response->assertJson(['name' => 'Alice']);

// Dot-notation path
$response->assertJsonPath('data.email', 'alice@example.com');

// Structure check (keys exist)
$response->assertJsonStructure(['id', 'name', 'email']);

// Nested structure
$response->assertJsonStructure([
    'data' => ['id', 'name', 'email'],
    'meta' => ['total', 'per_page'],
]);

// Count items
$response->assertJsonCount(5, 'data');

// Exact match
$response->assertExactJson(['status' => 'ok']);

// Missing values
$response->assertJsonMissing(['password' => 'secret']);
```

### Validation Error Assertions

```php
$response->assertUnprocessable();
$response->assertJsonValidationErrors(['email', 'name']);
$response->assertJsonMissingValidationErrors(['status']);
```

Supports both error formats:
- Keyed: `{ "errors": { "email": ["Required"] } }`
- List: `{ "errors": [{ "field": "email", "message": "Required" }] }`

### Header Assertions

```php
$response->assertHeader('Content-Type', 'application/json');
$response->assertHeaderMissing('X-Debug');
```

## Database Assertions

The `TestCase` base class provides database assertions using the PDO connection from the app container:

```php
// Row exists
$this->assertDatabaseHas('contacts', [
    'email' => 'alice@example.com',
    'status' => 'lead',
]);

// Row does not exist
$this->assertDatabaseMissing('contacts', [
    'email' => 'deleted@example.com',
]);

// Table has exact row count
$this->assertDatabaseCount('contacts', 5);
```

## Database Traits

### RefreshDatabase

Runs migrations before each test and truncates all tables after each test. Protected tables (like `migrations`) are never truncated:

```php
use Lattice\Testing\Traits\RefreshDatabase;

final class ContactTest extends TestCase
{
    use RefreshDatabase;

    protected array $protectedTables = ['migrations', 'settings'];

    protected function runApplicationMigrations(\PDO $pdo): void
    {
        // Define schema for this test suite
        $pdo->exec('CREATE TABLE contacts (id INTEGER PRIMARY KEY, name TEXT, email TEXT)');
    }
}
```

### DatabaseTransactions

Wraps each test in a transaction that rolls back after the test. Faster than `RefreshDatabase` when you do not need schema changes between tests:

```php
use Lattice\Testing\Traits\DatabaseTransactions;

final class ContactQueryTest extends TestCase
{
    use DatabaseTransactions;
    // Each test runs in a transaction -- rolled back in tearDown
}
```

## Authentication Traits

### WithAuthentication

Automatically creates a `FakePrincipal` and calls `actingAs()` before each test:

```php
use Lattice\Testing\Traits\WithAuthentication;

final class ProtectedEndpointTest extends TestCase
{
    use WithAuthentication;

    public function test_authenticated_user_can_access(): void
    {
        // $this->authenticatedUser is already set (FakePrincipal)
        $response = $this->getJson('/api/me');
        $response->assertOk();
    }

    // Override to customize the user
    protected function createAuthenticatedUser(): FakePrincipal
    {
        return new FakePrincipal(id: 'admin-1', type: 'user', roles: ['admin']);
    }
}
```

### WithWorkspace

Creates a workspace context and binds it into the container:

```php
use Lattice\Testing\Traits\WithWorkspace;

final class WorkspaceScopedTest extends TestCase
{
    use WithAuthentication;
    use WithWorkspace;

    public function test_contacts_scoped_to_workspace(): void
    {
        // $this->workspace is set with id, name, slug
        $response = $this->getJson('/api/contacts');
        $response->assertOk();
    }
}
```

## Fakes

### FakeEventBus

Captures dispatched events for assertion:

```php
$events = new FakeEventBus();

// Replace real event bus in container
$container->instance(EventBusInterface::class, $events);

// ... run code that dispatches events ...

$events->assertDispatched(ContactCreatedEvent::class);
$events->assertNotDispatched(ContactDeletedEvent::class);
$dispatched = $events->getDispatched(ContactCreatedEvent::class); // list<object>
```

### FakeQueueDispatcher

Captures dispatched jobs:

```php
$queue = new FakeQueueDispatcher();
$container->instance(QueueDispatcherInterface::class, $queue);

// ... run code that dispatches jobs ...

$queue->assertDispatched(SendWelcomeEmail::class);
$queue->assertNotDispatched(ProcessPayment::class);
```

### FakeAuthGuard

A guard that always allows and sets a configurable principal:

```php
$guard = new FakeAuthGuard(principal: new FakePrincipal(id: '1', roles: ['admin']));
```

## End-to-End Test Example

Based on the framework integration test, here is a complete CRUD lifecycle test:

```php
final class ContactCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_create_read_delete_cycle(): void
    {
        // CREATE
        $createResponse = $this->postJson('/api/contacts', [
            'name' => 'CRUD Test',
            'email' => 'crud@test.com',
            'status' => 'active',
        ]);
        $createResponse->assertCreated();
        $id = $createResponse->getBody()['id'];

        // READ
        $readResponse = $this->getJson("/api/contacts/{$id}");
        $readResponse->assertOk();
        $readResponse->assertJsonPath('name', 'CRUD Test');
        $readResponse->assertJsonPath('email', 'crud@test.com');

        // LIST
        $listResponse = $this->getJson('/api/contacts');
        $listResponse->assertOk();
        $listResponse->assertJsonCount(1);

        // DELETE
        $deleteResponse = $this->deleteJson("/api/contacts/{$id}");
        $deleteResponse->assertNoContent();

        // VERIFY DELETED
        $this->getJson("/api/contacts/{$id}")->assertNotFound();
        $this->assertDatabaseMissing('contacts', ['id' => $id]);
    }
}
```

## Guard and Pipeline Testing

Test that protected routes reject unauthenticated requests:

```php
public function test_protected_route_requires_auth(): void
{
    // No token
    $response = $this->getJson('/api/protected');
    $response->assertForbidden(); // or assertUnauthorized()
}

public function test_protected_route_with_valid_token(): void
{
    $response = $this->withToken('valid-token')
        ->getJson('/api/protected');
    $response->assertOk();
    $response->assertJsonPath('user_id', '1');
}
```

## Validation Testing

```php
public function test_create_contact_validates_email(): void
{
    $response = $this->postJson('/api/contacts', [
        'name' => 'Valid Name',
        'email' => 'not-an-email',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['email']);
}

public function test_create_contact_requires_name(): void
{
    $response = $this->postJson('/api/contacts', [
        'email' => 'test@example.com',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['name']);
}
```

## Running Tests

```bash
# Run all tests
vendor/bin/phpunit

# Run a specific test file
vendor/bin/phpunit packages/auth/tests/Integration/AuthFlowTest.php

# Run with filter
vendor/bin/phpunit --filter test_full_auth_lifecycle
```
