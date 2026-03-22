<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Unit;

use Lattice\Queue\Driver\DatabaseDriver;
use Lattice\Queue\SerializedJob;
use Lattice\Queue\Tests\Stub\TestJob;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(DatabaseDriver::class)]
final class DatabaseDriverTest extends TestCase
{
    private \PDO $pdo;
    private DatabaseDriver $driver;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->pdo->exec('
            CREATE TABLE queue_jobs (
                id TEXT PRIMARY KEY,
                queue TEXT NOT NULL,
                payload TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                max_attempts INTEGER NOT NULL DEFAULT 3,
                timeout INTEGER NOT NULL DEFAULT 60,
                available_at TEXT NOT NULL,
                created_at TEXT NOT NULL,
                reserved_at TEXT DEFAULT NULL,
                failed_at TEXT DEFAULT NULL,
                error TEXT DEFAULT NULL
            )
        ');

        $this->driver = new DatabaseDriver($this->pdo);
    }

    #[Test]
    public function it_pushes_a_job(): void
    {
        $job = SerializedJob::fromJob(new TestJob('db-test'));
        $id = $this->driver->push('default', $job);

        self::assertNotEmpty($id);
        self::assertSame(1, $this->driver->size('default'));
    }

    #[Test]
    public function it_pops_the_oldest_available_job(): void
    {
        $job1 = SerializedJob::fromJob(new TestJob('first'));
        $job2 = SerializedJob::fromJob(new TestJob('second'));

        $this->driver->push('default', $job1);
        $this->driver->push('default', $job2);

        $popped = $this->driver->pop('default');

        self::assertNotNull($popped);
        self::assertSame($job1->id, $popped->id);
    }

    #[Test]
    public function pop_returns_null_on_empty_queue(): void
    {
        self::assertNull($this->driver->pop('default'));
    }

    #[Test]
    public function it_marks_popped_job_as_reserved(): void
    {
        $job = SerializedJob::fromJob(new TestJob());
        $this->driver->push('default', $job);

        $this->driver->pop('default');

        // Popping again should return null because the job is reserved
        self::assertNull($this->driver->pop('default'));
    }

    #[Test]
    public function it_acknowledges_a_job_by_deleting_it(): void
    {
        $job = SerializedJob::fromJob(new TestJob());
        $this->driver->push('default', $job);
        $this->driver->pop('default');

        $this->driver->acknowledge($job->id);

        self::assertSame(0, $this->driver->size('default'));
    }

    #[Test]
    public function it_fails_a_job(): void
    {
        $job = SerializedJob::fromJob(new TestJob());
        $this->driver->push('default', $job);
        $this->driver->pop('default');

        $exception = new \RuntimeException('Something went wrong');
        $this->driver->fail($job->id, $exception);

        $stmt = $this->pdo->prepare('SELECT failed_at, error FROM queue_jobs WHERE id = ?');
        $stmt->execute([$job->id]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);

        self::assertNotNull($row['failed_at']);
        self::assertStringContainsString('Something went wrong', $row['error']);
    }

    #[Test]
    public function it_pushes_delayed_job(): void
    {
        $job = SerializedJob::fromJob(new TestJob());
        $id = $this->driver->pushDelayed('default', $job, 60);

        self::assertNotEmpty($id);

        // Should not be available yet
        $popped = $this->driver->pop('default');
        self::assertNull($popped);
    }

    #[Test]
    public function it_isolates_queues(): void
    {
        $this->driver->push('queue-a', SerializedJob::fromJob(new TestJob()));
        $this->driver->push('queue-b', SerializedJob::fromJob(new TestJob()));

        self::assertSame(1, $this->driver->size('queue-a'));
        self::assertSame(1, $this->driver->size('queue-b'));
    }

    #[Test]
    public function it_reports_correct_size(): void
    {
        self::assertSame(0, $this->driver->size('default'));

        $this->driver->push('default', SerializedJob::fromJob(new TestJob()));
        $this->driver->push('default', SerializedJob::fromJob(new TestJob()));

        self::assertSame(2, $this->driver->size('default'));
    }
}
