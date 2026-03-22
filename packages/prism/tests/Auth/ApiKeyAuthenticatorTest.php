<?php

declare(strict_types=1);

namespace Lattice\Prism\Tests\Auth;

use Lattice\Prism\Auth\ApiKeyAuthenticator;
use Lattice\Prism\Database\Project;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ApiKeyAuthenticatorTest extends TestCase
{
    private ApiKeyAuthenticator $auth;

    protected function setUp(): void
    {
        $this->auth = new ApiKeyAuthenticator();
    }

    #[Test]
    public function test_authenticate_valid_key(): void
    {
        $rawKey = 'my-secret-api-key';
        $project = new Project(
            id: 'proj-1',
            name: 'Test Project',
            apiKeyHash: Project::hashApiKey($rawKey),
            createdAt: date('c'),
        );

        $this->auth->registerProject($project);

        $result = $this->auth->authenticate($rawKey);

        $this->assertNotNull($result);
        $this->assertSame('proj-1', $result->id);
    }

    #[Test]
    public function test_authenticate_invalid_key(): void
    {
        $project = new Project(
            id: 'proj-1',
            name: 'Test Project',
            apiKeyHash: Project::hashApiKey('correct-key'),
            createdAt: date('c'),
        );

        $this->auth->registerProject($project);

        $result = $this->auth->authenticate('wrong-key');

        $this->assertNull($result);
    }

    #[Test]
    public function test_authenticate_empty_key(): void
    {
        $this->assertNull($this->auth->authenticate(''));
    }

    #[Test]
    public function test_authenticate_no_projects(): void
    {
        $this->assertNull($this->auth->authenticate('any-key'));
    }

    #[Test]
    public function test_authenticate_multiple_projects(): void
    {
        $key1 = 'key-for-project-1';
        $key2 = 'key-for-project-2';

        $this->auth->registerProject(new Project('p1', 'Project 1', Project::hashApiKey($key1), date('c')));
        $this->auth->registerProject(new Project('p2', 'Project 2', Project::hashApiKey($key2), date('c')));

        $result1 = $this->auth->authenticate($key1);
        $result2 = $this->auth->authenticate($key2);

        $this->assertNotNull($result1);
        $this->assertSame('p1', $result1->id);

        $this->assertNotNull($result2);
        $this->assertSame('p2', $result2->id);
    }

    #[Test]
    public function test_extract_key_from_x_prism_key_header(): void
    {
        $key = $this->auth->extractKey(['X-Prism-Key' => 'my-api-key']);

        $this->assertSame('my-api-key', $key);
    }

    #[Test]
    public function test_extract_key_from_bearer_token(): void
    {
        $key = $this->auth->extractKey(['Authorization' => 'Bearer my-api-key']);

        $this->assertSame('my-api-key', $key);
    }

    #[Test]
    public function test_x_prism_key_takes_precedence(): void
    {
        $key = $this->auth->extractKey([
            'X-Prism-Key' => 'prism-key',
            'Authorization' => 'Bearer bearer-key',
        ]);

        $this->assertSame('prism-key', $key);
    }

    #[Test]
    public function test_extract_key_case_insensitive_headers(): void
    {
        $key = $this->auth->extractKey(['x-prism-key' => 'my-key']);

        $this->assertSame('my-key', $key);
    }

    #[Test]
    public function test_extract_key_no_headers(): void
    {
        $this->assertNull($this->auth->extractKey([]));
    }

    #[Test]
    public function test_extract_key_invalid_bearer_format(): void
    {
        // Only "Bearer " prefix is supported
        $this->assertNull($this->auth->extractKey(['Authorization' => 'Basic abc123']));
    }

    #[Test]
    public function test_extract_key_empty_bearer(): void
    {
        $this->assertNull($this->auth->extractKey(['Authorization' => 'Bearer ']));
    }

    #[Test]
    public function test_project_hash_verification(): void
    {
        $rawKey = 'test-key-123';
        $hash = Project::hashApiKey($rawKey);

        $project = new Project('p1', 'Test', $hash, date('c'));

        $this->assertTrue($project->verifyApiKey($rawKey));
        $this->assertFalse($project->verifyApiKey('wrong-key'));
    }

    #[Test]
    public function test_reset(): void
    {
        $this->auth->registerProject(new Project('p1', 'Test', Project::hashApiKey('key'), date('c')));

        $this->assertNotNull($this->auth->authenticate('key'));

        $this->auth->reset();

        $this->assertNull($this->auth->authenticate('key'));
    }
}
