<?php

declare(strict_types=1);

namespace Lattice\Prism\Tests\Api;

use DateTimeImmutable;
use Lattice\Prism\Api\IssueListAction;
use Lattice\Prism\Database\IssueRepository;
use Lattice\Prism\Event\ErrorEvent;
use Lattice\Prism\Event\ErrorLevel;
use Lattice\Prism\Event\IssueStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IssueListActionTest extends TestCase
{
    private IssueRepository $repo;
    private IssueListAction $action;

    protected function setUp(): void
    {
        $this->repo = new IssueRepository();
        $this->action = new IssueListAction($this->repo);
    }

    #[Test]
    public function test_requires_project_id(): void
    {
        $result = ($this->action)([]);

        $this->assertSame(422, $result['status']);
        $this->assertStringContainsString('project_id', $result['error']);
    }

    #[Test]
    public function test_lists_issues_for_project(): void
    {
        $this->seedIssues();

        $result = ($this->action)(['project_id' => 'proj-1']);

        $this->assertSame(200, $result['status']);
        $this->assertCount(3, $result['data']);
        $this->assertSame(3, $result['meta']['total']);
    }

    #[Test]
    public function test_filter_by_status(): void
    {
        $this->seedIssues();
        $issues = $this->repo->listByProject('proj-1');
        $this->repo->updateStatus($issues[0]->id, IssueStatus::Resolved);

        $result = ($this->action)(['project_id' => 'proj-1', 'status' => 'unresolved']);

        $this->assertSame(200, $result['status']);
        $this->assertCount(2, $result['data']);
    }

    #[Test]
    public function test_filter_by_level(): void
    {
        $this->seedIssues();

        $result = ($this->action)(['project_id' => 'proj-1', 'level' => 'warning']);

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']);
    }

    #[Test]
    public function test_search(): void
    {
        $this->seedIssues();

        $result = ($this->action)(['project_id' => 'proj-1', 'search' => 'TypeError']);

        $this->assertSame(200, $result['status']);
        $this->assertCount(1, $result['data']);
    }

    #[Test]
    public function test_pagination(): void
    {
        $this->seedIssues();

        $page1 = ($this->action)(['project_id' => 'proj-1', 'limit' => '2', 'offset' => '0']);
        $page2 = ($this->action)(['project_id' => 'proj-1', 'limit' => '2', 'offset' => '2']);

        $this->assertCount(2, $page1['data']);
        $this->assertCount(1, $page2['data']);
        $this->assertSame(3, $page1['meta']['total']);
    }

    #[Test]
    public function test_empty_project(): void
    {
        $result = ($this->action)(['project_id' => 'empty-project']);

        $this->assertSame(200, $result['status']);
        $this->assertCount(0, $result['data']);
        $this->assertSame(0, $result['meta']['total']);
    }

    private function seedIssues(): void
    {
        $event1 = new ErrorEvent(
            eventId: 'e1',
            timestamp: new DateTimeImmutable(),
            projectId: 'proj-1',
            environment: 'production',
            platform: 'php',
            level: ErrorLevel::Error,
            exceptionType: 'RuntimeException',
            exceptionMessage: 'Connection refused',
        );

        $event2 = new ErrorEvent(
            eventId: 'e2',
            timestamp: new DateTimeImmutable(),
            projectId: 'proj-1',
            environment: 'production',
            platform: 'php',
            level: ErrorLevel::Warning,
            exceptionType: 'DeprecationWarning',
            exceptionMessage: 'Method deprecated',
        );

        $event3 = new ErrorEvent(
            eventId: 'e3',
            timestamp: new DateTimeImmutable(),
            projectId: 'proj-1',
            environment: 'staging',
            platform: 'javascript',
            level: ErrorLevel::Error,
            exceptionType: 'TypeError',
            exceptionMessage: 'undefined is not a function',
        );

        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-1', $event1);
        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-2', $event2);
        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-3', $event3);
    }
}
