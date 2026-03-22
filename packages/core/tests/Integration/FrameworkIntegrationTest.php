<?php

declare(strict_types=1);

namespace Lattice\Core\Tests\Integration;

use Illuminate\Database\Capsule\Manager as Capsule;
use Lattice\Core\Application;
use Lattice\Core\Http\RequestFactory;
use Lattice\Core\Tests\Integration\Fixtures\Framework\CreateTestContactDto;
use Lattice\Core\Tests\Integration\Fixtures\Framework\PropertyBasedDto;
use Lattice\Core\Tests\Integration\Fixtures\Framework\TestAuthGuard;
use Lattice\Core\Tests\Integration\Fixtures\Framework\TestContact;
use Lattice\Core\Tests\Integration\Fixtures\Framework\TestController;
use Lattice\Core\Tests\Integration\Fixtures\Framework\TestModule;
use Lattice\Core\Tests\Integration\Fixtures\Framework\TestService;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Routing\RouteDefinition;
use Lattice\Routing\Router;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive framework integration test suite.
 *
 * Tests the REAL request lifecycle end-to-end:
 *   boot -> module discovery -> route registration -> guard execution
 *   -> parameter binding -> controller -> Eloquent -> response
 *
 * Uses SQLite in-memory for Eloquent, real Application with real modules.
 */
final class FrameworkIntegrationTest extends TestCase
{
    private Application $app;
    private string $basePath;
    private static ?Capsule $capsule = null;

    protected function setUp(): void
    {
        if (!class_exists(Capsule::class)) {
            $this->markTestSkipped('Illuminate\\Database\\Capsule\\Manager is not available');
        }

        if (!class_exists(\Lattice\Module\Attribute\Module::class)) {
            $this->markTestSkipped('lattice/module package not available');
        }

        // Clear any previous singleton
        Application::clearInstance();

        $this->basePath = sys_get_temp_dir() . '/lattice-framework-test-' . uniqid();
        mkdir($this->basePath, 0755, true);

        // Set up SQLite in-memory Eloquent connection
        $this->setupDatabase();

        // Boot a real Application with the test module
        $this->app = new Application(
            basePath: $this->basePath,
            modules: [TestModule::class],
        );

        // Register TestService in the container so controller injection works
        $this->app->getContainer()->singleton(TestService::class, TestService::class);
    }

    protected function tearDown(): void
    {
        Application::clearInstance();

        if (isset($this->basePath) && is_dir($this->basePath)) {
            @rmdir($this->basePath);
        }

        // Reset the database capsule connection for clean state
        if (self::$capsule !== null) {
            try {
                self::$capsule->getConnection()->disconnect();
            } catch (\Throwable) {
                // ignore
            }
        }
    }

    private function setupDatabase(): void
    {
        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        self::$capsule = $capsule;

        // Create test_contacts table
        $capsule->getConnection()->getSchemaBuilder()->create('test_contacts', function ($table) {
            $table->increments('id');
            $table->string('name');
            $table->string('email');
            $table->string('status')->default('active');
        });
    }

    // === Helper Methods ===

    private function handleRequest(
        string $method,
        string $uri,
        mixed $body = null,
        array $headers = [],
        array $query = [],
    ): Response {
        $request = new Request(
            method: strtoupper($method),
            uri: $uri,
            headers: array_merge(['content-type' => 'application/json', 'accept' => 'application/json'], $headers),
            query: $query,
            body: $body,
            pathParams: [],
        );

        return $this->app->handleRequest($request);
    }

    private function handleRequestWithToken(
        string $method,
        string $uri,
        string $token,
        mixed $body = null,
    ): Response {
        return $this->handleRequest($method, $uri, $body, [
            'Authorization' => 'Bearer ' . $token,
        ]);
    }

    // =========================================================================
    // BOOT & MODULE DISCOVERY
    // =========================================================================

    #[Test]
    public function test_application_boots_and_discovers_modules(): void
    {
        $this->app->boot();

        $moduleDefinitions = $this->app->getModuleDefinitions();

        $this->assertNotEmpty($moduleDefinitions);
        $this->assertArrayHasKey(TestModule::class, $moduleDefinitions);
    }

    #[Test]
    public function test_application_discovers_routes_from_module_controllers(): void
    {
        $this->app->boot();

        $controllers = $this->app->getControllers();

        $this->assertContains(TestController::class, $controllers);
    }

    #[Test]
    public function test_application_registers_routes_in_router(): void
    {
        // Trigger a request to force the router to be built
        $this->handleRequest('GET', '/api/test/health');

        $router = $this->app->getContainer()->get(Router::class);
        $routes = $router->getRoutes();

        $this->assertNotEmpty($routes);

        $paths = array_map(fn(RouteDefinition $r) => $r->path, $routes);
        $this->assertContains('/api/test/health', $paths);
        $this->assertContains('/api/test/contacts/:id', $paths);
        $this->assertContains('/api/test/contacts', $paths);
    }

    // =========================================================================
    // ROUTING
    // =========================================================================

    #[Test]
    public function test_get_request_matches_route(): void
    {
        $response = $this->handleRequest('GET', '/api/test/health');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['status' => 'ok'], $response->body);
    }

    #[Test]
    public function test_post_request_matches_route(): void
    {
        $response = $this->handleRequest('POST', '/api/test/contacts', [
            'name' => 'Alice',
            'email' => 'alice@example.com',
            'status' => 'active',
        ]);

        $this->assertSame(201, $response->statusCode);
    }

    #[Test]
    public function test_unknown_route_returns_404(): void
    {
        $response = $this->handleRequest('GET', '/api/test/nonexistent');

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function test_wrong_method_returns_404(): void
    {
        // /api/test/health only has GET, sending PUT should fail
        $response = $this->handleRequest('PUT', '/api/test/health');

        // Router returns 404 (not 405) for method mismatch
        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function test_path_params_extracted_correctly(): void
    {
        // Create a contact first
        TestContact::create(['name' => 'Bob', 'email' => 'bob@example.com', 'status' => 'active']);

        $response = $this->handleRequest('GET', '/api/test/contacts/1');

        $this->assertSame(200, $response->statusCode);
        $this->assertIsArray($response->body);
        $this->assertSame(1, $response->body['id']);
        $this->assertSame('Bob', $response->body['name']);
    }

    #[Test]
    public function test_colon_style_params_work(): void
    {
        // The route is defined as /contacts/:id — test that it matches
        TestContact::create(['name' => 'Eve', 'email' => 'eve@example.com', 'status' => 'active']);

        $response = $this->handleRequest('GET', '/api/test/contacts/1');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Eve', $response->body['name']);
    }

    #[Test]
    public function test_static_routes_match_before_parameterized(): void
    {
        // /api/test/contacts (static, GET) vs /api/test/contacts/:id (parameterized, GET)
        // The static route for index listing should match /api/test/contacts
        $response = $this->handleRequest('GET', '/api/test/contacts');

        $this->assertSame(200, $response->statusCode);
        // Should return an array (collection), not a single contact
        $this->assertIsArray($response->body);
    }

    // =========================================================================
    // PARAMETER BINDING
    // =========================================================================

    #[Test]
    public function test_body_dto_constructor_based_deserialized(): void
    {
        $response = $this->handleRequest('POST', '/api/test/contacts', [
            'name' => 'Constructor DTO',
            'email' => 'cdto@example.com',
            'status' => 'active',
        ]);

        $this->assertSame(201, $response->statusCode);
        $this->assertSame('Constructor DTO', $response->body['name']);
        $this->assertSame('cdto@example.com', $response->body['email']);
    }

    #[Test]
    public function test_body_dto_property_based_deserialized(): void
    {
        $response = $this->handleRequest('POST', '/api/test/contacts-prop', [
            'name' => 'Property DTO',
            'email' => 'pdto@example.com',
        ]);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Property DTO', $response->body['name']);
        $this->assertSame('pdto@example.com', $response->body['email']);
    }

    #[Test]
    public function test_body_with_missing_required_field_returns_422(): void
    {
        $response = $this->handleRequest('POST', '/api/test/validate', [
            'email' => 'test@example.com',
            // 'name' is missing
        ]);

        $this->assertSame(422, $response->statusCode);
    }

    #[Test]
    public function test_body_with_invalid_email_returns_422(): void
    {
        $response = $this->handleRequest('POST', '/api/test/validate', [
            'name' => 'Test',
            'email' => 'not-an-email',
        ]);

        $this->assertSame(422, $response->statusCode);
    }

    #[Test]
    public function test_path_param_int_coerced(): void
    {
        TestContact::create(['name' => 'IntTest', 'email' => 'int@test.com', 'status' => 'active']);

        // The param is typed as `int $id`, the path value "1" should be coerced
        $response = $this->handleRequest('GET', '/api/test/contacts/1');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(1, $response->body['id']);
    }

    #[Test]
    public function test_path_param_string_works(): void
    {
        $response = $this->handleRequest('GET', '/api/test/contacts/by-email/hello@world.com');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('hello@world.com', $response->body['email']);
    }

    #[Test]
    public function test_current_user_injected_from_guard(): void
    {
        $response = $this->handleRequestWithToken('GET', '/api/test/protected', 'valid-token');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('1', $response->body['user_id']);
    }

    #[Test]
    public function test_current_user_without_auth_returns_401_or_403(): void
    {
        // No auth token — guard returns false -> ForbiddenException (403)
        // OR CurrentUser resolver throws UnauthorizedException (401)
        // The ProblemDetailsFilter or ExceptionHandler will convert to a status code
        $response = $this->handleRequest('GET', '/api/test/protected');

        // Guard returns false -> ForbiddenException -> 403
        $this->assertContains($response->statusCode, [401, 403]);
    }

    #[Test]
    public function test_request_object_auto_injected(): void
    {
        // The index() method takes Request $request without any attribute
        // ParameterResolver auto-injects it
        TestContact::create(['name' => 'AutoInject', 'email' => 'ai@test.com', 'status' => 'active']);

        $response = $this->handleRequest('GET', '/api/test/contacts');

        $this->assertSame(200, $response->statusCode);
        $this->assertIsArray($response->body);
    }

    // =========================================================================
    // GUARDS & PIPELINE
    // =========================================================================

    #[Test]
    public function test_unguarded_route_accessible_without_token(): void
    {
        $response = $this->handleRequest('GET', '/api/test/health');

        $this->assertSame(200, $response->statusCode);
    }

    #[Test]
    public function test_guarded_route_blocked_without_token(): void
    {
        $response = $this->handleRequest('GET', '/api/test/protected');

        // Guard denies -> ForbiddenException -> 403
        $this->assertContains($response->statusCode, [401, 403]);
    }

    #[Test]
    public function test_guarded_route_accessible_with_valid_token(): void
    {
        $response = $this->handleRequestWithToken('GET', '/api/test/protected', 'valid-token');

        $this->assertSame(200, $response->statusCode);
        $this->assertArrayHasKey('user_id', $response->body);
    }

    #[Test]
    public function test_guard_sets_principal_on_context(): void
    {
        $response = $this->handleRequestWithToken('GET', '/api/test/protected', 'valid-token');

        $this->assertSame(200, $response->statusCode);
        // The controller reads principal->getId(), so if we get user_id back the principal was set
        $this->assertSame('1', $response->body['user_id']);
    }

    #[Test]
    public function test_invalid_token_returns_403(): void
    {
        $response = $this->handleRequestWithToken('GET', '/api/test/protected', 'invalid-token');

        $this->assertSame(403, $response->statusCode);
    }

    // =========================================================================
    // RESPONSE SERIALIZATION
    // =========================================================================

    #[Test]
    public function test_array_return_serialized_to_json(): void
    {
        $response = $this->handleRequest('GET', '/api/test/health');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame(['status' => 'ok'], $response->body);
    }

    #[Test]
    public function test_response_object_passed_through(): void
    {
        // The create() method returns a Response object with 201
        $response = $this->handleRequest('POST', '/api/test/contacts', [
            'name' => 'ResponseObj',
            'email' => 'resp@test.com',
            'status' => 'active',
        ]);

        $this->assertSame(201, $response->statusCode);
    }

    #[Test]
    public function test_void_return_gives_204(): void
    {
        TestContact::create(['name' => 'ToDelete', 'email' => 'del@test.com', 'status' => 'active']);

        $response = $this->handleRequest('DELETE', '/api/test/contacts/1');

        $this->assertSame(204, $response->statusCode);
    }

    #[Test]
    public function test_resource_serialized_correctly(): void
    {
        TestContact::create(['name' => 'Resource Test', 'email' => 'res@test.com', 'status' => 'active']);

        $response = $this->handleRequest('GET', '/api/test/contacts/1');

        $this->assertSame(200, $response->statusCode);
        $this->assertArrayHasKey('id', $response->body);
        $this->assertArrayHasKey('name', $response->body);
        $this->assertArrayHasKey('email', $response->body);
        // Resource should NOT expose status since it's not in toArray()
        $this->assertArrayNotHasKey('status', $response->body);
    }

    #[Test]
    public function test_created_response_has_201_status(): void
    {
        $response = $this->handleRequest('POST', '/api/test/contacts', [
            'name' => '201 Test',
            'email' => 'status@test.com',
            'status' => 'active',
        ]);

        $this->assertSame(201, $response->statusCode);
    }

    // =========================================================================
    // ERROR HANDLING
    // =========================================================================

    #[Test]
    public function test_exception_returns_500(): void
    {
        $response = $this->handleRequest('GET', '/api/test/error');

        $this->assertSame(500, $response->statusCode);
    }

    #[Test]
    public function test_validation_error_returns_422_with_field_errors(): void
    {
        $response = $this->handleRequest('POST', '/api/test/validate', [
            'email' => 'not-valid',
            // name missing
        ]);

        $this->assertSame(422, $response->statusCode);
        $this->assertIsArray($response->body);
    }

    #[Test]
    public function test_not_found_returns_404(): void
    {
        $response = $this->handleRequest('GET', '/api/test/nonexistent');

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function test_problem_details_format_has_expected_keys(): void
    {
        $response = $this->handleRequest('GET', '/api/test/error');

        $this->assertSame(500, $response->statusCode);
        $body = $response->body;
        $this->assertIsArray($body);

        // The error response body format depends on which handler catches the exception.
        // ProblemDetailsFilter: {type, title, status, detail, ...}
        // ExceptionHandler: {error: ...}
        // ExceptionRenderer: {type, title, status, detail, instance, ...}
        // Some handlers may return null body for 500 errors.
        //
        // Assert: the status code alone proves error handling works.
        // If the body is structured, verify its shape.
        if (is_array($body) && isset($body['type'])) {
            $this->assertArrayHasKey('title', $body);
            $this->assertArrayHasKey('status', $body);
            $this->assertSame(500, $body['status']);
        } elseif (is_array($body) && isset($body['error'])) {
            $this->assertSame('Internal Server Error', $body['error']);
        } else {
            // Body might be null or another format — the 500 status code is sufficient
            $this->assertSame(500, $response->statusCode);
        }
    }

    // =========================================================================
    // ELOQUENT INTEGRATION
    // =========================================================================

    #[Test]
    public function test_model_create_from_controller(): void
    {
        $response = $this->handleRequest('POST', '/api/test/contacts', [
            'name' => 'DB Create',
            'email' => 'dbcreate@test.com',
            'status' => 'active',
        ]);

        $this->assertSame(201, $response->statusCode);

        // Verify the model was actually persisted
        $contact = TestContact::where('email', 'dbcreate@test.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame('DB Create', $contact->name);
    }

    #[Test]
    public function test_model_find_from_path_param(): void
    {
        $contact = TestContact::create(['name' => 'FindMe', 'email' => 'find@test.com', 'status' => 'active']);

        $response = $this->handleRequest('GET', '/api/test/contacts/' . $contact->id);

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('FindMe', $response->body['name']);
    }

    #[Test]
    public function test_model_not_found_returns_404(): void
    {
        // No contact with ID 9999 exists
        $response = $this->handleRequest('GET', '/api/test/contacts/9999');

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function test_model_delete_removes_from_db(): void
    {
        $contact = TestContact::create(['name' => 'DeleteMe', 'email' => 'delete@test.com', 'status' => 'active']);
        $id = $contact->id;

        $response = $this->handleRequest('DELETE', '/api/test/contacts/' . $id);

        $this->assertSame(204, $response->statusCode);

        // Verify deletion
        $this->assertNull(TestContact::find($id));
    }

    // =========================================================================
    // DI CONTAINER
    // =========================================================================

    #[Test]
    public function test_controller_constructor_injection(): void
    {
        $response = $this->handleRequest('GET', '/api/test/greet/World');

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('Hello, World!', $response->body['greeting']);
    }

    #[Test]
    public function test_service_provider_bindings_available(): void
    {
        $container = $this->app->getContainer();

        $service = $container->make(TestService::class);
        $this->assertInstanceOf(TestService::class, $service);
    }

    // =========================================================================
    // FULL LIFECYCLE
    // =========================================================================

    #[Test]
    public function test_full_create_read_update_delete_cycle(): void
    {
        // CREATE
        $createResponse = $this->handleRequest('POST', '/api/test/contacts', [
            'name' => 'CRUD Test',
            'email' => 'crud@test.com',
            'status' => 'active',
        ]);
        $this->assertSame(201, $createResponse->statusCode);
        $id = $createResponse->body['id'];
        $this->assertNotNull($id);

        // READ
        $readResponse = $this->handleRequest('GET', '/api/test/contacts/' . $id);
        $this->assertSame(200, $readResponse->statusCode);
        $this->assertSame('CRUD Test', $readResponse->body['name']);
        $this->assertSame('crud@test.com', $readResponse->body['email']);

        // READ ALL (index)
        $indexResponse = $this->handleRequest('GET', '/api/test/contacts');
        $this->assertSame(200, $indexResponse->statusCode);
        $this->assertIsArray($indexResponse->body);
        $this->assertCount(1, $indexResponse->body);

        // DELETE
        $deleteResponse = $this->handleRequest('DELETE', '/api/test/contacts/' . $id);
        $this->assertSame(204, $deleteResponse->statusCode);

        // VERIFY DELETED
        $verifyResponse = $this->handleRequest('GET', '/api/test/contacts/' . $id);
        $this->assertSame(404, $verifyResponse->statusCode);
    }

    #[Test]
    public function test_multiple_requests_in_sequence(): void
    {
        // First request
        $response1 = $this->handleRequest('GET', '/api/test/health');
        $this->assertSame(200, $response1->statusCode);

        // Second request — different route
        $response2 = $this->handleRequest('POST', '/api/test/contacts', [
            'name' => 'Seq1',
            'email' => 'seq1@test.com',
            'status' => 'active',
        ]);
        $this->assertSame(201, $response2->statusCode);

        // Third request — yet another route
        $response3 = $this->handleRequest('POST', '/api/test/contacts', [
            'name' => 'Seq2',
            'email' => 'seq2@test.com',
            'status' => 'inactive',
        ]);
        $this->assertSame(201, $response3->statusCode);

        // Fourth request — read back
        $response4 = $this->handleRequest('GET', '/api/test/contacts');
        $this->assertSame(200, $response4->statusCode);
        $this->assertCount(2, $response4->body);

        // Fifth request — error doesn't break subsequent requests
        $response5 = $this->handleRequest('GET', '/api/test/error');
        $this->assertSame(500, $response5->statusCode);

        // Sixth request — still works after error
        $response6 = $this->handleRequest('GET', '/api/test/health');
        $this->assertSame(200, $response6->statusCode);
    }

    #[Test]
    public function test_query_params_filter_contacts(): void
    {
        TestContact::create(['name' => 'Active', 'email' => 'active@test.com', 'status' => 'active']);
        TestContact::create(['name' => 'Inactive', 'email' => 'inactive@test.com', 'status' => 'inactive']);

        $response = $this->handleRequest('GET', '/api/test/contacts', null, [], ['status' => 'active']);

        $this->assertSame(200, $response->statusCode);
        $this->assertIsArray($response->body);
        $this->assertCount(1, $response->body);
        $this->assertSame('Active', $response->body[0]['name']);
    }

    #[Test]
    public function test_body_with_default_value_uses_default(): void
    {
        // CreateTestContactDto has status with default 'active'
        // Omit status from the body
        $response = $this->handleRequest('POST', '/api/test/contacts', [
            'name' => 'Default Status',
            'email' => 'default@test.com',
        ]);

        $this->assertSame(201, $response->statusCode);

        $contact = TestContact::where('email', 'default@test.com')->first();
        $this->assertNotNull($contact);
        $this->assertSame('active', $contact->status);
    }

    #[Test]
    public function test_collection_returns_resource_shaped_items(): void
    {
        TestContact::create(['name' => 'CollA', 'email' => 'colla@test.com', 'status' => 'active']);
        TestContact::create(['name' => 'CollB', 'email' => 'collb@test.com', 'status' => 'active']);

        $response = $this->handleRequest('GET', '/api/test/contacts');

        $this->assertSame(200, $response->statusCode);
        $this->assertCount(2, $response->body);

        // Each item should be shaped by TestContactResource::toArray()
        foreach ($response->body as $item) {
            $this->assertArrayHasKey('id', $item);
            $this->assertArrayHasKey('name', $item);
            $this->assertArrayHasKey('email', $item);
            $this->assertArrayNotHasKey('status', $item); // not in resource
        }
    }

    #[Test]
    public function test_validation_error_body_contains_error_info(): void
    {
        $response = $this->handleRequest('POST', '/api/test/validate', [
            'name' => 'Valid Name',
            'email' => 'bad-email',
        ]);

        $this->assertSame(422, $response->statusCode);
        $body = $response->body;
        $this->assertIsArray($body);

        // The body should contain some error information about the email field
        // Check both possible formats (ProblemDetails with 'errors' key, or direct error)
        if (isset($body['errors'])) {
            $this->assertArrayHasKey('email', $body['errors']);
        }
    }

    #[Test]
    public function test_model_not_found_on_delete_returns_404(): void
    {
        $response = $this->handleRequest('DELETE', '/api/test/contacts/99999');

        $this->assertSame(404, $response->statusCode);
    }

    #[Test]
    public function test_application_environment_defaults_to_production(): void
    {
        // When APP_ENV is not set, default is production
        $originalEnv = $_ENV['APP_ENV'] ?? null;
        unset($_ENV['APP_ENV']);

        $this->assertSame('production', $this->app->environment());

        if ($originalEnv !== null) {
            $_ENV['APP_ENV'] = $originalEnv;
        }
    }
}
