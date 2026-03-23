<?php

declare(strict_types=1);

namespace Lattice\Testing;

use Lattice\Testing\Http\TestResponse;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * Base test case for LatticePHP applications.
 *
 * Provides HTTP testing helpers, database assertions, authentication helpers,
 * and automatic trait setUp/tearDown discovery.
 */
abstract class TestCase extends PHPUnitTestCase
{
    protected ?object $app = null;

    /** @var array<string, string> */
    private array $defaultHeaders = [];

    private ?string $authToken = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app = $this->createApplication();

        // Run trait setUp methods (e.g., setUpRefreshDatabase)
        foreach ($this->getTraitSetUpMethods() as $method) {
            $this->{$method}();
        }
    }

    protected function tearDown(): void
    {
        // Run trait tearDown methods (e.g., tearDownRefreshDatabase)
        foreach ($this->getTraitTearDownMethods() as $method) {
            $this->{$method}();
        }

        $this->app = null;
        $this->defaultHeaders = [];
        $this->authToken = null;

        // Clear the Application singleton if the class exists
        if (class_exists(\Lattice\Core\Application::class)) {
            \Lattice\Core\Application::clearInstance();
        }

        parent::tearDown();
    }

    /**
     * Create the application instance for this test.
     *
     * Override in subclasses to provide a custom bootstrap.
     * Returns object (not typed to Application) so the testing package
     * can be used without requiring lattice/core.
     */
    protected function createApplication(): ?object
    {
        if (!class_exists(\Lattice\Core\Application::class)) {
            return null;
        }

        return \Lattice\Core\Application::configure(basePath: getcwd())
            ->withModules($this->getModules())
            ->withHttp()
            ->create();
    }

    /**
     * Get the modules to register in the test application.
     *
     * @return list<class-string>
     */
    protected function getModules(): array
    {
        return [];
    }

    // --- HTTP Testing Methods ---

    /**
     * Set a Bearer token for all subsequent requests.
     */
    public function withToken(string $token): static
    {
        $this->authToken = $token;
        return $this;
    }

    /**
     * Clear the Bearer token so subsequent requests have no Authorization header.
     */
    public function withoutAuth(): static
    {
        $this->authToken = null;
        unset($this->defaultHeaders['authorization']);
        return $this;
    }

    /**
     * Merge headers into the default set for all subsequent requests.
     *
     * @param array<string, string> $headers
     */
    public function withHeaders(array $headers): static
    {
        $this->defaultHeaders = array_merge($this->defaultHeaders, $headers);
        return $this;
    }

    /**
     * Set a single default header for all subsequent requests.
     */
    public function withHeader(string $name, string $value): static
    {
        $this->defaultHeaders[$name] = $value;
        return $this;
    }

    /**
     * Authenticate as the given user, bypassing real auth.
     */
    public function actingAs(object $user, ?object $workspace = null): static
    {
        if ($this->app !== null && method_exists($this->app, 'getContainer')) {
            $container = $this->app->getContainer();
            $container->instance('auth.user', $user);
            if ($workspace !== null) {
                $container->instance('workspace.current', $workspace);
            }
        }
        return $this;
    }

    public function getJson(string $uri, array $headers = []): TestResponse
    {
        return $this->json('GET', $uri, null, $headers);
    }

    public function postJson(string $uri, mixed $data = null, array $headers = []): TestResponse
    {
        return $this->json('POST', $uri, $data, $headers);
    }

    public function putJson(string $uri, mixed $data = null, array $headers = []): TestResponse
    {
        return $this->json('PUT', $uri, $data, $headers);
    }

    public function patchJson(string $uri, mixed $data = null, array $headers = []): TestResponse
    {
        return $this->json('PATCH', $uri, $data, $headers);
    }

    public function deleteJson(string $uri, array $headers = []): TestResponse
    {
        return $this->json('DELETE', $uri, null, $headers);
    }

    /**
     * Send a JSON request through the application.
     *
     * @param array<string, string> $headers
     */
    protected function json(string $method, string $uri, mixed $data = null, array $headers = []): TestResponse
    {
        $allHeaders = array_merge($this->defaultHeaders, $headers);
        $allHeaders['content-type'] = 'application/json';
        $allHeaders['accept'] = 'application/json';

        if ($this->authToken !== null) {
            $allHeaders['authorization'] = 'Bearer ' . $this->authToken;
        }

        if ($this->app === null || !method_exists($this->app, 'handleRequest')) {
            throw new \RuntimeException(
                'Cannot send HTTP requests: Application not available. '
                . 'Override createApplication() to provide a testable app instance.'
            );
        }

        $request = new \Lattice\Http\Request(
            method: $method,
            uri: $this->extractPath($uri),
            headers: $allHeaders,
            query: $this->parseQueryString($uri),
            body: $this->normalizeBody($data),
            pathParams: [],
        );

        $response = $this->app->handleRequest($request);

        return new TestResponse(
            status: $response->statusCode,
            headers: $response->headers,
            body: $response->body,
        );
    }

    /**
     * Get the default headers for requests.
     *
     * @return array<string, string>
     */
    public function getDefaultHeaders(): array
    {
        return $this->defaultHeaders;
    }

    /**
     * Get the currently set auth token.
     */
    public function getAuthToken(): ?string
    {
        return $this->authToken;
    }

    // --- Test Helpers ---

    /**
     * Boot an in-memory SQLite database for testing.
     * Returns the IlluminateDatabaseManager instance.
     *
     * @param array<string, mixed> $config
     */
    protected function bootTestDatabase(array $config = []): object
    {
        $defaults = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ];

        $dbConfig = array_merge($defaults, $config);

        if (!class_exists(\Lattice\Database\Illuminate\IlluminateDatabaseManager::class)) {
            throw new \RuntimeException('lattice/database is required for test database support');
        }

        return new \Lattice\Database\Illuminate\IlluminateDatabaseManager($dbConfig);
    }

    /**
     * Generate a test JWT token for the given user model.
     */
    protected function generateTestToken(object $user, string $secret = 'test-secret'): string
    {
        if (!class_exists(\Lattice\Jwt\JwtEncoder::class)) {
            throw new \RuntimeException('lattice/jwt is required for test token generation');
        }

        $encoder = new \Lattice\Jwt\JwtEncoder();

        return $encoder->encode([
            'sub' => (string) ($user->id ?? $user->getId()),
            'email' => $user->email ?? '',
            'roles' => [$user->role ?? 'user'],
            'iat' => time(),
            'exp' => time() + 3600,
        ], $secret);
    }

    // --- Database Assertions ---

    /**
     * Assert that a row matching the given data exists in the table.
     *
     * @param array<string, mixed> $data
     */
    public function assertDatabaseHas(string $table, array $data): void
    {
        $pdo = $this->getDatabaseConnection();
        $count = $this->countMatchingRows($pdo, $table, $data);
        $this->assertGreaterThan(
            0,
            $count,
            sprintf('No matching row found in table "%s": %s', $table, json_encode($data))
        );
    }

    /**
     * Assert that no row matching the given data exists in the table.
     *
     * @param array<string, mixed> $data
     */
    public function assertDatabaseMissing(string $table, array $data): void
    {
        $pdo = $this->getDatabaseConnection();
        $count = $this->countMatchingRows($pdo, $table, $data);
        $this->assertEquals(
            0,
            $count,
            sprintf('Unexpected matching row found in table "%s": %s', $table, json_encode($data))
        );
    }

    /**
     * Assert that a table has the expected number of rows.
     */
    public function assertDatabaseCount(string $table, int $expected): void
    {
        $pdo = $this->getDatabaseConnection();
        $count = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
        $this->assertEquals(
            $expected,
            $count,
            sprintf('Expected %d rows in table "%s", found %d.', $expected, $table, $count)
        );
    }

    /**
     * Get the database connection for assertions.
     *
     * Tries the app container first, falls back to SQLite in-memory.
     */
    protected function getDatabaseConnection(): \PDO
    {
        if ($this->app !== null && method_exists($this->app, 'getContainer')) {
            $container = $this->app->getContainer();
            if ($container->has(\PDO::class)) {
                return $container->get(\PDO::class);
            }
        }

        return new \PDO('sqlite::memory:');
    }

    // --- Private Helpers ---

    private function parseQueryString(string $uri): array
    {
        $query = [];
        $parts = parse_url($uri);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        return $query;
    }

    private function extractPath(string $uri): string
    {
        $parts = parse_url($uri);
        return $parts['path'] ?? $uri;
    }

    private function normalizeBody(mixed $data): mixed
    {
        if ($data === null) {
            return [];
        }
        if (is_array($data)) {
            return $data;
        }
        return json_decode(json_encode($data), true);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function countMatchingRows(\PDO $pdo, string $table, array $data): int
    {
        $conditions = [];
        $values = [];
        foreach ($data as $col => $val) {
            $conditions[] = "\"{$col}\" = ?";
            $values[] = $val;
        }
        $sql = "SELECT COUNT(*) FROM \"{$table}\" WHERE " . implode(' AND ', $conditions);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
        return (int) $stmt->fetchColumn();
    }

    /**
     * Discover trait setUp methods following the convention setUp{TraitBaseName}.
     *
     * @return list<string>
     */
    private function getTraitSetUpMethods(): array
    {
        $methods = [];
        foreach ($this->getUsedTraits() as $trait) {
            $method = 'setUp' . $this->classBaseName($trait);
            if (method_exists($this, $method)) {
                $methods[] = $method;
            }
        }
        return $methods;
    }

    /**
     * Discover trait tearDown methods following the convention tearDown{TraitBaseName}.
     *
     * @return list<string>
     */
    private function getTraitTearDownMethods(): array
    {
        $methods = [];
        foreach ($this->getUsedTraits() as $trait) {
            $method = 'tearDown' . $this->classBaseName($trait);
            if (method_exists($this, $method)) {
                $methods[] = $method;
            }
        }
        return $methods;
    }

    /**
     * Get all traits used by this class and its parents, recursively.
     *
     * @return list<class-string>
     */
    private function getUsedTraits(): array
    {
        $traits = [];
        $class = static::class;

        while ($class !== false) {
            $traits = array_merge($traits, array_values(class_uses($class) ?: []));
            $class = get_parent_class($class);
        }

        // Also resolve traits used by traits
        $resolved = [];
        while ($traits !== []) {
            $trait = array_pop($traits);
            if (!in_array($trait, $resolved, true)) {
                $resolved[] = $trait;
                $traitTraits = class_uses($trait) ?: [];
                $traits = array_merge($traits, array_values($traitTraits));
            }
        }

        return $resolved;
    }

    private function classBaseName(string $class): string
    {
        $parts = explode('\\', $class);
        return end($parts);
    }
}
