<?php

declare(strict_types=1);

namespace Lattice\Prism\Tests\Database;

use DateTimeImmutable;
use Lattice\Prism\Database\IssueRepository;
use Lattice\Prism\Event\ErrorEvent;
use Lattice\Prism\Event\ErrorLevel;
use Lattice\Prism\Event\IssueStatus;
use Lattice\Prism\Event\StackFrame;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IssueRepositoryTest extends TestCase
{
    private IssueRepository $repo;

    protected function setUp(): void
    {
        $this->repo = new IssueRepository();
    }

    #[Test]
    public function test_find_or_create_creates_new_issue(): void
    {
        $event = $this->makeEvent();

        $result = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-abc123', $event);

        $this->assertTrue($result['is_new']);
        $this->assertFalse($result['is_regression']);
        $this->assertSame('proj-1', $result['issue']->projectId);
        $this->assertSame('fp-abc123', $result['issue']->fingerprint);
        $this->assertSame(1, $result['issue']->count);
        $this->assertSame(IssueStatus::Unresolved, $result['issue']->status);
        $this->assertSame('RuntimeException: Something went wrong', $result['issue']->title);
    }

    #[Test]
    public function test_find_or_create_returns_existing_issue(): void
    {
        $event = $this->makeEvent();

        $result1 = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-abc123', $event);
        $result2 = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-abc123', $event);

        $this->assertTrue($result1['is_new']);
        $this->assertFalse($result2['is_new']);
        $this->assertSame($result1['issue']->id, $result2['issue']->id);
        $this->assertSame(2, $result2['issue']->count);
    }

    #[Test]
    public function test_increment_count_on_duplicate(): void
    {
        $event = $this->makeEvent();

        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-abc', $event);
        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-abc', $event);
        $result = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-abc', $event);

        $this->assertSame(3, $result['issue']->count);
    }

    #[Test]
    public function test_regression_detection(): void
    {
        $event = $this->makeEvent();

        // Create and resolve
        $result1 = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-abc', $event);
        $this->repo->updateStatus($result1['issue']->id, IssueStatus::Resolved);

        // Verify resolved
        $resolved = $this->repo->findById($result1['issue']->id);
        $this->assertSame(IssueStatus::Resolved, $resolved->status);

        // New event for same fingerprint => regression
        $result2 = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-abc', $event);

        $this->assertTrue($result2['is_regression']);
        $this->assertSame(IssueStatus::Unresolved, $result2['issue']->status);
    }

    #[Test]
    public function test_different_fingerprints_different_issues(): void
    {
        $event = $this->makeEvent();

        $result1 = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-aaa', $event);
        $result2 = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-bbb', $event);

        $this->assertNotSame($result1['issue']->id, $result2['issue']->id);
        $this->assertTrue($result1['is_new']);
        $this->assertTrue($result2['is_new']);
    }

    #[Test]
    public function test_same_fingerprint_different_projects(): void
    {
        $event = $this->makeEvent();

        $result1 = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-abc', $event);
        $result2 = $this->repo->findOrCreateByFingerprint('proj-2', 'fp-abc', $event);

        $this->assertNotSame($result1['issue']->id, $result2['issue']->id);
        $this->assertTrue($result1['is_new']);
        $this->assertTrue($result2['is_new']);
    }

    #[Test]
    public function test_find_by_id(): void
    {
        $event = $this->makeEvent();
        $result = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-abc', $event);

        $found = $this->repo->findById($result['issue']->id);

        $this->assertNotNull($found);
        $this->assertSame($result['issue']->id, $found->id);
    }

    #[Test]
    public function test_find_by_id_not_found(): void
    {
        $this->assertNull($this->repo->findById('nonexistent'));
    }

    #[Test]
    public function test_list_by_project(): void
    {
        $event = $this->makeEvent();

        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-1', $event);
        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-2', $event);
        $this->repo->findOrCreateByFingerprint('proj-2', 'fp-3', $event);

        $proj1Issues = $this->repo->listByProject('proj-1');
        $proj2Issues = $this->repo->listByProject('proj-2');

        $this->assertCount(2, $proj1Issues);
        $this->assertCount(1, $proj2Issues);
    }

    #[Test]
    public function test_list_by_project_filter_status(): void
    {
        $event = $this->makeEvent();

        $r1 = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-1', $event);
        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-2', $event);
        $this->repo->updateStatus($r1['issue']->id, IssueStatus::Resolved);

        $unresolved = $this->repo->listByProject('proj-1', ['status' => 'unresolved']);
        $resolved = $this->repo->listByProject('proj-1', ['status' => 'resolved']);

        $this->assertCount(1, $unresolved);
        $this->assertCount(1, $resolved);
    }

    #[Test]
    public function test_list_by_project_filter_level(): void
    {
        $errorEvent = $this->makeEvent(level: ErrorLevel::Error);
        $warningEvent = $this->makeEvent(level: ErrorLevel::Warning);

        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-err', $errorEvent);
        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-warn', $warningEvent);

        $errors = $this->repo->listByProject('proj-1', ['level' => 'error']);
        $warnings = $this->repo->listByProject('proj-1', ['level' => 'warning']);

        $this->assertCount(1, $errors);
        $this->assertCount(1, $warnings);
    }

    #[Test]
    public function test_list_by_project_search(): void
    {
        $event1 = $this->makeEvent(exceptionType: 'RuntimeException', exceptionMessage: 'Connection refused');
        $event2 = $this->makeEvent(exceptionType: 'TypeError', exceptionMessage: 'undefined is not a function');

        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-1', $event1);
        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-2', $event2);

        $results = $this->repo->listByProject('proj-1', ['search' => 'connection']);
        $this->assertCount(1, $results);
        $this->assertStringContainsString('Connection', $results[0]->title);
    }

    #[Test]
    public function test_list_by_project_pagination(): void
    {
        $event = $this->makeEvent();

        for ($i = 0; $i < 10; $i++) {
            $this->repo->findOrCreateByFingerprint('proj-1', "fp-$i", $event);
        }

        $page1 = $this->repo->listByProject('proj-1', limit: 3, offset: 0);
        $page2 = $this->repo->listByProject('proj-1', limit: 3, offset: 3);

        $this->assertCount(3, $page1);
        $this->assertCount(3, $page2);
        $this->assertNotSame($page1[0]->id, $page2[0]->id);
    }

    #[Test]
    public function test_count_by_project(): void
    {
        $event = $this->makeEvent();

        for ($i = 0; $i < 5; $i++) {
            $this->repo->findOrCreateByFingerprint('proj-1', "fp-$i", $event);
        }

        $this->assertSame(5, $this->repo->countByProject('proj-1'));
        $this->assertSame(0, $this->repo->countByProject('proj-nonexistent'));
    }

    #[Test]
    public function test_update_status(): void
    {
        $event = $this->makeEvent();
        $result = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-1', $event);

        $updated = $this->repo->updateStatus($result['issue']->id, IssueStatus::Ignored);

        $this->assertNotNull($updated);
        $this->assertSame(IssueStatus::Ignored, $updated->status);

        // Verify persisted
        $fetched = $this->repo->findById($result['issue']->id);
        $this->assertSame(IssueStatus::Ignored, $fetched->status);
    }

    #[Test]
    public function test_update_status_nonexistent(): void
    {
        $result = $this->repo->updateStatus('nonexistent', IssueStatus::Resolved);
        $this->assertNull($result);
    }

    #[Test]
    public function test_last_seen_updated(): void
    {
        $event1 = new ErrorEvent(
            eventId: 'e1',
            timestamp: new DateTimeImmutable('2026-03-22T10:00:00+00:00'),
            projectId: 'proj-1',
            environment: 'production',
            platform: 'php',
            level: ErrorLevel::Error,
            exceptionType: 'RuntimeException',
            exceptionMessage: 'err',
        );

        $event2 = new ErrorEvent(
            eventId: 'e2',
            timestamp: new DateTimeImmutable('2026-03-22T14:00:00+00:00'),
            projectId: 'proj-1',
            environment: 'production',
            platform: 'php',
            level: ErrorLevel::Error,
            exceptionType: 'RuntimeException',
            exceptionMessage: 'err',
        );

        $this->repo->findOrCreateByFingerprint('proj-1', 'fp-1', $event1);
        $result = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-1', $event2);

        $this->assertStringContainsString('2026-03-22T14:00:00', $result['issue']->lastSeen);
    }

    #[Test]
    public function test_culprit_built_from_top_frame(): void
    {
        $event = new ErrorEvent(
            eventId: 'e1',
            timestamp: new DateTimeImmutable(),
            projectId: 'proj-1',
            environment: 'production',
            platform: 'php',
            level: ErrorLevel::Error,
            exceptionType: 'RuntimeException',
            exceptionMessage: 'err',
            stacktrace: [
                new StackFrame(file: '/app/src/Controller.php', line: 42, function: 'index', class: 'App\\Controller'),
            ],
        );

        $result = $this->repo->findOrCreateByFingerprint('proj-1', 'fp-1', $event);
        $this->assertSame('/app/src/Controller.php:42', $result['issue']->culprit);
    }

    private function makeEvent(
        ErrorLevel $level = ErrorLevel::Error,
        string $exceptionType = 'RuntimeException',
        string $exceptionMessage = 'Something went wrong',
    ): ErrorEvent {
        return new ErrorEvent(
            eventId: '550e8400-e29b-41d4-a716-446655440000',
            timestamp: new DateTimeImmutable(),
            projectId: 'proj-1',
            environment: 'production',
            platform: 'php',
            level: $level,
            exceptionType: $exceptionType,
            exceptionMessage: $exceptionMessage,
        );
    }
}
