<?php

declare(strict_types=1);

namespace Lattice\Testing\Tests\Unit;

use Lattice\Testing\Fakes\FakePrincipal;
use Lattice\Testing\Http\TestResponse;
use Lattice\Testing\TestCase;
use Lattice\Testing\TestContainer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase as PHPUnitTestCase;

/**
 * We test the TestCase using a concrete stub that doesn't require lattice/core.
 *
 * StubTestCase overrides createApplication() to return a minimal fake app
 * that records requests and returns canned responses.
 */
final class TestCaseTest extends PHPUnitTestCase
{
    #[Test]
    public function test_with_headers_adds_headers(): void
    {
        $tc = new StubTestCase('test_with_headers_adds_headers');
        $tc->setUp();

        $tc->withHeaders(['X-Custom' => 'foo', 'X-Another' => 'bar']);

        $this->assertSame('foo', $tc->getDefaultHeaders()['X-Custom']);
        $this->assertSame('bar', $tc->getDefaultHeaders()['X-Another']);

        $tc->tearDown();
    }

    #[Test]
    public function test_with_headers_merges_incrementally(): void
    {
        $tc = new StubTestCase('test_with_headers_merges_incrementally');
        $tc->setUp();

        $tc->withHeaders(['X-First' => '1']);
        $tc->withHeaders(['X-Second' => '2']);

        $this->assertSame('1', $tc->getDefaultHeaders()['X-First']);
        $this->assertSame('2', $tc->getDefaultHeaders()['X-Second']);

        $tc->tearDown();
    }

    #[Test]
    public function test_with_header_sets_single_header(): void
    {
        $tc = new StubTestCase('test_with_header_sets_single_header');
        $tc->setUp();

        $tc->withHeader('X-Single', 'value');

        $this->assertSame('value', $tc->getDefaultHeaders()['X-Single']);

        $tc->tearDown();
    }

    #[Test]
    public function test_with_token_sets_auth_token(): void
    {
        $tc = new StubTestCase('test_with_token_sets_auth_token');
        $tc->setUp();

        $tc->withToken('my-jwt-token');

        $this->assertSame('my-jwt-token', $tc->getAuthToken());

        $tc->tearDown();
    }

    #[Test]
    public function test_with_token_returns_self_for_chaining(): void
    {
        $tc = new StubTestCase('test_with_token_returns_self_for_chaining');
        $tc->setUp();

        $result = $tc->withToken('token');

        $this->assertSame($tc, $result);

        $tc->tearDown();
    }

    #[Test]
    public function test_acting_as_sets_user_on_container(): void
    {
        $tc = new StubTestCase('test_acting_as_sets_user_on_container');
        $tc->setUp();

        $user = new FakePrincipal(id: 'user-42');
        $tc->actingAs($user);

        /** @var FakeApp $app */
        $app = $tc->getApp();
        $this->assertSame($user, $app->getContainer()->get('auth.user'));

        $tc->tearDown();
    }

    #[Test]
    public function test_acting_as_sets_workspace_on_container(): void
    {
        $tc = new StubTestCase('test_acting_as_sets_workspace_on_container');
        $tc->setUp();

        $user = new FakePrincipal(id: 'user-42');
        $workspace = new \stdClass();
        $workspace->id = 'ws-1';
        $tc->actingAs($user, $workspace);

        /** @var FakeApp $app */
        $app = $tc->getApp();
        $this->assertSame($workspace, $app->getContainer()->get('workspace.current'));

        $tc->tearDown();
    }

    #[Test]
    public function test_tear_down_resets_state(): void
    {
        $tc = new StubTestCase('test_tear_down_resets_state');
        $tc->setUp();

        $tc->withHeaders(['X-Test' => 'value']);
        $tc->withToken('some-token');

        $tc->tearDown();

        $this->assertSame([], $tc->getDefaultHeaders());
        $this->assertNull($tc->getAuthToken());
        $this->assertNull($tc->getApp());
    }

    #[Test]
    public function test_assert_database_has_finds_matching_row(): void
    {
        $tc = new StubTestCaseWithDb('test_assert_database_has_finds_matching_row');
        $tc->setUp();
        $tc->seedTestData();

        // Should not throw
        $tc->assertDatabaseHas('users', ['name' => 'Alice']);

        $tc->tearDown();
    }

    #[Test]
    public function test_assert_database_has_fails_on_no_match(): void
    {
        $tc = new StubTestCaseWithDb('test_assert_database_has_fails_on_no_match');
        $tc->setUp();
        $tc->seedTestData();

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $tc->assertDatabaseHas('users', ['name' => 'NonExistent']);

        $tc->tearDown();
    }

    #[Test]
    public function test_assert_database_missing_passes_when_no_match(): void
    {
        $tc = new StubTestCaseWithDb('test_assert_database_missing_passes_when_no_match');
        $tc->setUp();
        $tc->seedTestData();

        // Should not throw
        $tc->assertDatabaseMissing('users', ['name' => 'NonExistent']);

        $tc->tearDown();
    }

    #[Test]
    public function test_assert_database_missing_fails_when_match_exists(): void
    {
        $tc = new StubTestCaseWithDb('test_assert_database_missing_fails_when_match_exists');
        $tc->setUp();
        $tc->seedTestData();

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $tc->assertDatabaseMissing('users', ['name' => 'Alice']);

        $tc->tearDown();
    }

    #[Test]
    public function test_assert_database_count_passes_with_correct_count(): void
    {
        $tc = new StubTestCaseWithDb('test_assert_database_count_passes_with_correct_count');
        $tc->setUp();
        $tc->seedTestData();

        $tc->assertDatabaseCount('users', 2);

        $tc->tearDown();
    }

    #[Test]
    public function test_assert_database_count_fails_with_wrong_count(): void
    {
        $tc = new StubTestCaseWithDb('test_assert_database_count_fails_with_wrong_count');
        $tc->setUp();
        $tc->seedTestData();

        $this->expectException(\PHPUnit\Framework\AssertionFailedError::class);
        $tc->assertDatabaseCount('users', 5);

        $tc->tearDown();
    }
}

// --- Test Doubles ---

/**
 * Fake application that records requests and returns canned responses.
 */
class FakeApp
{
    private TestContainer $container;

    public function __construct()
    {
        $this->container = new TestContainer();
    }

    public function getContainer(): TestContainer
    {
        return $this->container;
    }

    public function handleRequest(object $request): object
    {
        // Return a simple response-like object
        return new class {
            public int $statusCode = 200;
            public array $headers = ['Content-Type' => 'application/json'];
            public mixed $body = ['ok' => true];
        };
    }
}

/**
 * Concrete TestCase that uses a FakeApp instead of the real Application.
 */
class StubTestCase extends TestCase
{
    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    protected function createApplication(): ?object
    {
        return new FakeApp();
    }

    public function getApp(): ?object
    {
        return $this->app;
    }

    // Make public for testing
    public function setUp(): void
    {
        parent::setUp();
    }

    public function tearDown(): void
    {
        parent::tearDown();
    }
}

/**
 * Concrete TestCase with a real SQLite PDO for database assertion tests.
 */
class StubTestCaseWithDb extends TestCase
{
    private ?\PDO $pdo = null;

    public function __construct(string $name)
    {
        parent::__construct($name);
    }

    protected function createApplication(): ?object
    {
        return new FakeApp();
    }

    public function setUp(): void
    {
        parent::setUp();
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
    }

    public function tearDown(): void
    {
        $this->pdo = null;
        parent::tearDown();
    }

    protected function getDatabaseConnection(): \PDO
    {
        return $this->pdo;
    }

    public function seedTestData(): void
    {
        $this->pdo->exec('CREATE TABLE "users" ("id" INTEGER PRIMARY KEY, "name" TEXT, "email" TEXT)');
        $this->pdo->exec('INSERT INTO "users" ("name", "email") VALUES (\'Alice\', \'alice@example.com\')');
        $this->pdo->exec('INSERT INTO "users" ("name", "email") VALUES (\'Bob\', \'bob@example.com\')');
    }
}
