<?php

declare(strict_types=1);

namespace Lattice\Prism\Tests\Api;

use Lattice\Prism\Api\IngestAction;
use Lattice\Prism\Auth\ApiKeyAuthenticator;
use Lattice\Prism\Database\IssueRepository;
use Lattice\Prism\Database\Project;
use Lattice\Prism\Fingerprint\Fingerprinter;
use Lattice\Prism\Storage\LocalFilesystemStorage;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IngestActionTest extends TestCase
{
    private string $tempDir;
    private IngestAction $action;
    private ApiKeyAuthenticator $auth;
    private IssueRepository $issueRepo;
    private string $apiKey;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/prism_ingest_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);

        $storage = new LocalFilesystemStorage($this->tempDir);
        $fingerprinter = new Fingerprinter();
        $this->issueRepo = new IssueRepository();
        $this->auth = new ApiKeyAuthenticator();

        // Register a test project
        $this->apiKey = 'test-api-key-12345';
        $project = new Project(
            id: 'proj-1',
            name: 'Test Project',
            apiKeyHash: Project::hashApiKey($this->apiKey),
            createdAt: date('c'),
        );
        $this->auth->registerProject($project);

        $this->action = new IngestAction($storage, $fingerprinter, $this->issueRepo, $this->auth);
    }

    protected function tearDown(): void
    {
        $this->removeDir($this->tempDir);
    }

    #[Test]
    public function test_valid_single_event_returns_202(): void
    {
        $result = ($this->action)(
            $this->validEventBody(),
            ['X-Prism-Key' => $this->apiKey],
        );

        $this->assertSame(202, $result['status']);
        $this->assertArrayHasKey('event_ids', $result);
        $this->assertCount(1, $result['event_ids']);
    }

    #[Test]
    public function test_missing_api_key_returns_401(): void
    {
        $result = ($this->action)($this->validEventBody(), []);

        $this->assertSame(401, $result['status']);
        $this->assertStringContainsString('Missing API key', $result['error']);
    }

    #[Test]
    public function test_invalid_api_key_returns_403(): void
    {
        $result = ($this->action)(
            $this->validEventBody(),
            ['X-Prism-Key' => 'wrong-key'],
        );

        $this->assertSame(403, $result['status']);
        $this->assertStringContainsString('Invalid API key', $result['error']);
    }

    #[Test]
    public function test_bearer_token_auth(): void
    {
        $result = ($this->action)(
            $this->validEventBody(),
            ['Authorization' => 'Bearer ' . $this->apiKey],
        );

        $this->assertSame(202, $result['status']);
    }

    #[Test]
    public function test_invalid_event_returns_422(): void
    {
        $body = $this->validEventBody();
        unset($body['level']);

        $result = ($this->action)(
            $body,
            ['X-Prism-Key' => $this->apiKey],
        );

        $this->assertSame(422, $result['status']);
        $this->assertStringContainsString('level', $result['error']);
    }

    #[Test]
    public function test_batch_ingestion(): void
    {
        $batch = [
            $this->validEventBody(),
            $this->validEventBody(),
            $this->validEventBody(),
        ];

        $result = ($this->action)(
            $batch,
            ['X-Prism-Key' => $this->apiKey],
        );

        $this->assertSame(202, $result['status']);
        $this->assertCount(3, $result['accepted']);
        $this->assertSame([], $result['rejected']);
    }

    #[Test]
    public function test_batch_partial_success(): void
    {
        $batch = [
            $this->validEventBody(),
            ['invalid' => 'data'], // Missing required fields
            $this->validEventBody(),
        ];

        $result = ($this->action)(
            $batch,
            ['X-Prism-Key' => $this->apiKey],
        );

        $this->assertSame(202, $result['status']);
        $this->assertCount(2, $result['accepted']);
        $this->assertCount(1, $result['rejected']);
        $this->assertSame(1, $result['rejected'][0]['index']);
    }

    #[Test]
    public function test_batch_size_limit(): void
    {
        $batch = array_fill(0, 101, $this->validEventBody());

        $result = ($this->action)(
            $batch,
            ['X-Prism-Key' => $this->apiKey],
        );

        $this->assertSame(422, $result['status']);
        $this->assertStringContainsString('exceeds maximum of 100', $result['error']);
    }

    #[Test]
    public function test_creates_issue_on_first_event(): void
    {
        ($this->action)(
            $this->validEventBody(),
            ['X-Prism-Key' => $this->apiKey],
        );

        $issues = $this->issueRepo->listByProject('proj-1');
        $this->assertCount(1, $issues);
        $this->assertSame(1, $issues[0]->count);
    }

    #[Test]
    public function test_increments_issue_count_on_duplicate(): void
    {
        $body = $this->validEventBody();

        ($this->action)($body, ['X-Prism-Key' => $this->apiKey]);
        ($this->action)($body, ['X-Prism-Key' => $this->apiKey]);
        ($this->action)($body, ['X-Prism-Key' => $this->apiKey]);

        $issues = $this->issueRepo->listByProject('proj-1');
        $this->assertCount(1, $issues);
        $this->assertSame(3, $issues[0]->count);
    }

    #[Test]
    public function test_regression_detection_on_resolved_issue(): void
    {
        $body = $this->validEventBody();

        // Ingest first event
        $result1 = ($this->action)($body, ['X-Prism-Key' => $this->apiKey]);
        $issueId = $result1['event_ids'][0]; // We need the issue ID

        // Find the issue and resolve it
        $issues = $this->issueRepo->listByProject('proj-1');
        $this->issueRepo->updateStatus($issues[0]->id, \Lattice\Prism\Event\IssueStatus::Resolved);

        // Ingest same event again — should trigger regression
        $result2 = ($this->action)($body, ['X-Prism-Key' => $this->apiKey]);

        // In batch mode the response has 'accepted', in single mode 'event_ids'
        $this->assertSame(202, $result2['status']);

        // Verify issue is now unresolved
        $issue = $this->issueRepo->findById($issues[0]->id);
        $this->assertSame(\Lattice\Prism\Event\IssueStatus::Unresolved, $issue->status);
    }

    #[Test]
    public function test_project_id_injected_from_auth(): void
    {
        $body = $this->validEventBody();
        $body['project_id'] = 'attacker-project'; // should be overridden

        $result = ($this->action)(
            $body,
            ['X-Prism-Key' => $this->apiKey],
        );

        $this->assertSame(202, $result['status']);

        $issues = $this->issueRepo->listByProject('proj-1');
        $this->assertCount(1, $issues);

        $attackerIssues = $this->issueRepo->listByProject('attacker-project');
        $this->assertCount(0, $attackerIssues);
    }

    /**
     * @return array<string, mixed>
     */
    private function validEventBody(): array
    {
        return [
            'environment' => 'production',
            'platform' => 'php',
            'level' => 'error',
            'exception' => [
                'type' => 'RuntimeException',
                'value' => 'Something went wrong',
                'stacktrace' => [
                    [
                        'file' => '/app/src/Service.php',
                        'line' => 42,
                        'function' => 'handle',
                        'class' => 'App\\Service',
                    ],
                ],
            ],
            'tags' => ['browser' => 'Chrome'],
        ];
    }

    private function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $entries = scandir($dir);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir . '/' . $entry;
            if (is_dir($path)) {
                $this->removeDir($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
