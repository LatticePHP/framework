<?php

declare(strict_types=1);

namespace Tests;

use App\AppModule;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Note;
use App\Models\User;
use Lattice\Auth\Models\Workspace;
use Lattice\Auth\Models\WorkspaceInvitation;
use Lattice\Auth\Workspace\WorkspaceContext;
use Lattice\Database\Illuminate\IlluminateDatabaseManager;
use Lattice\Jwt\JwtEncoder;
use Lattice\Testing\TestCase as LatticeTestCase;
use Lattice\Testing\Traits\RefreshDatabase;

/**
 * Base test case for CRM integration tests.
 *
 * Boots the full application with all CRM modules,
 * uses SQLite in-memory for isolation, and provides
 * helpers for authentication and workspace context.
 */
abstract class TestCase extends LatticeTestCase
{
    use RefreshDatabase;

    private const string TEST_JWT_SECRET = 'test-secret-key-for-crm-tests';

    protected ?User $user = null;
    protected ?Workspace $workspace = null;
    private ?IlluminateDatabaseManager $db = null;

    protected function setUp(): void
    {
        // Set JWT secret before app boots so AuthServiceProvider picks it up
        $_ENV['JWT_SECRET'] = self::TEST_JWT_SECRET;
        $_ENV['APP_DEBUG'] = 'true';

        // Clear Eloquent's booted state so traits re-register events with the new DB
        User::clearBootedModels();
        Contact::clearBootedModels();
        Company::clearBootedModels();
        Deal::clearBootedModels();
        Activity::clearBootedModels();
        Note::clearBootedModels();
        Workspace::clearBootedModels();
        WorkspaceInvitation::clearBootedModels();

        // Reset workspace context from previous test
        WorkspaceContext::reset();

        // Boot Capsule before parent::setUp() so migrations can use Capsule::schema()
        $this->db = new IlluminateDatabaseManager([
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        parent::setUp();

        // Default: create a user and workspace, act as that user in that workspace
        $this->user = $this->createUser();
        $this->workspace = $this->createTestWorkspace($this->user);
        $this->actingAs($this->user, $this->workspace);
        $this->withHeader('X-Workspace-Id', (string) $this->workspace->id);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->db = null;
    }

    /** @return list<class-string> */
    protected function getModules(): array
    {
        return [AppModule::class];
    }

    protected function getDatabaseConnection(): \PDO
    {
        if ($this->db !== null) {
            return $this->db->connection()->getPdo();
        }

        return parent::getDatabaseConnection();
    }

    /**
     * Run the CRM migration files against the test database.
     */
    protected function runApplicationMigrations(\PDO $pdo): void
    {
        $migrationsPath = dirname(__DIR__) . '/database/migrations';
        $files = glob($migrationsPath . '/*.php');
        sort($files);

        foreach ($files as $file) {
            $migration = require $file;
            if (is_object($migration) && method_exists($migration, 'up')) {
                $migration->up();
            }
        }
    }

    /**
     * Create a test user in the database.
     *
     * @param array<string, mixed> $overrides
     */
    protected function createUser(array $overrides = []): User
    {
        $defaults = [
            'name' => 'Test User',
            'email' => 'test-' . bin2hex(random_bytes(4)) . '@example.com',
            'password' => 'password123',
            'role' => 'admin',
        ];

        return User::create(array_merge($defaults, $overrides));
    }

    /**
     * Create a workspace owned by the given user.
     */
    protected function createTestWorkspace(User $owner): Workspace
    {
        $workspace = Workspace::create([
            'name' => 'Test Workspace',
            'slug' => 'test-ws-' . bin2hex(random_bytes(4)),
            'owner_id' => $owner->id,
        ]);

        // Add the owner as a member
        $workspace->members()->attach($owner->id, [
            'role' => 'owner',
            'joined_at' => now(),
        ]);

        return $workspace;
    }

    /**
     * Override actingAs to generate a real JWT Bearer token.
     */
    public function actingAs(object $user, ?object $workspace = null): static
    {
        parent::actingAs($user, $workspace);

        // Generate a real JWT so the JwtAuthenticationGuard passes
        $encoder = new JwtEncoder();
        $token = $encoder->encode([
            'sub' => (string) $user->id,
            'email' => $user->email ?? '',
            'roles' => [$user->role ?? 'user'],
            'iat' => time(),
            'exp' => time() + 3600,
        ], self::TEST_JWT_SECRET);

        $this->withToken($token);

        return $this;
    }

    /**
     * Authenticate as a specific user and set workspace context.
     */
    protected function actingAsUser(User $user, ?Workspace $workspace = null): static
    {
        $this->actingAs($user, $workspace ?? $this->workspace);

        if ($workspace !== null) {
            $this->withHeader('X-Workspace-Id', (string) $workspace->id);
        }

        return $this;
    }

    /**
     * Make a request with no authentication (anonymous).
     */
    protected function asGuest(): static
    {
        if ($this->app !== null && method_exists($this->app, 'getContainer')) {
            $container = $this->app->getContainer();
            $container->instance('auth.user', null);
            $container->instance('workspace.current', null);
        }

        // Clear the auth token and workspace header
        $this->withoutAuth();
        $this->withHeaders(['X-Workspace-Id' => '']);

        return $this;
    }
}
