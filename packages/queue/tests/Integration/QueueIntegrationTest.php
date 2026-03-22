<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Integration;

use Lattice\Queue\Dispatcher;
use Lattice\Queue\Driver\InMemoryDriver;
use Lattice\Queue\Driver\SyncDriver;
use Lattice\Queue\Facades\Queue;
use Lattice\Queue\Failed\InMemoryFailedJobStore;
use Lattice\Queue\RetryPolicy;
use Lattice\Queue\SerializedJob;
use Lattice\Queue\Testing\QueueFake;
use Lattice\Queue\Worker;
use Lattice\Queue\WorkerOptions;
use Lattice\Queue\Tests\Fixtures\AlwaysFailingJob;
use Lattice\Queue\Tests\Fixtures\BackoffTrackingJob;
use Lattice\Queue\Tests\Fixtures\CounterJob;
use Lattice\Queue\Tests\Fixtures\DatabaseJob;
use Lattice\Queue\Tests\Fixtures\EmailQueueJob;
use Lattice\Queue\Tests\Fixtures\FailingThenSucceedingJob;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class QueueIntegrationTest extends TestCase
{
    private InMemoryDriver $driver;
    private InMemoryFailedJobStore $failedStore;
    private RetryPolicy $retryPolicy;
    private Worker $worker;
    private Dispatcher $dispatcher;

    protected function setUp(): void
    {
        $this->driver = new InMemoryDriver();
        $this->failedStore = new InMemoryFailedJobStore();
        $this->retryPolicy = new RetryPolicy();
        $this->worker = new Worker($this->driver, $this->retryPolicy, $this->failedStore);
        $this->dispatcher = new Dispatcher($this->driver);

        // Reset all fixture state
        CounterJob::reset();
        FailingThenSucceedingJob::reset();
        AlwaysFailingJob::reset();
        EmailQueueJob::reset();
        DatabaseJob::reset();

        // Reset the Queue facade
        Queue::reset();
    }

    protected function tearDown(): void
    {
        Queue::reset();
    }

    // ---------------------------------------------------------------
    // 1. Sync execution: SyncDriver executes job immediately
    // ---------------------------------------------------------------
    #[Test]
    public function test_sync_driver_executes_job_immediately(): void
    {
        $syncDriver = new SyncDriver();
        $syncDispatcher = new Dispatcher($syncDriver);

        $job = new CounterJob('sync-test');
        $syncDispatcher->dispatch($job);

        self::assertSame(1, CounterJob::$counter);
        self::assertSame(['sync-test'], CounterJob::$log);
    }

    // ---------------------------------------------------------------
    // 2. In-memory queue: dispatch stores, worker pops and executes
    // ---------------------------------------------------------------
    #[Test]
    public function test_in_memory_driver_stores_and_worker_executes(): void
    {
        $job = new CounterJob('in-memory-test');

        // Dispatch stores the job
        $jobId = $this->dispatcher->dispatch($job);
        self::assertNotEmpty($jobId);
        self::assertSame(1, $this->driver->size('default'));
        self::assertSame(0, CounterJob::$counter, 'Job should not be executed yet');

        // Worker pops and processes
        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 1));

        self::assertSame(0, $this->driver->size('default'));
        self::assertSame(1, CounterJob::$counter);
        self::assertSame(['in-memory-test'], CounterJob::$log);
    }

    // ---------------------------------------------------------------
    // 3. Worker loop: processes multiple jobs in sequence
    // ---------------------------------------------------------------
    #[Test]
    public function test_worker_processes_multiple_jobs_in_sequence(): void
    {
        $this->dispatcher->dispatch(new CounterJob('first'));
        $this->dispatcher->dispatch(new CounterJob('second'));
        $this->dispatcher->dispatch(new CounterJob('third'));

        self::assertSame(3, $this->driver->size('default'));

        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 3));

        self::assertSame(0, $this->driver->size('default'));
        self::assertSame(3, CounterJob::$counter);
        self::assertSame(['first', 'second', 'third'], CounterJob::$log);
    }

    // ---------------------------------------------------------------
    // 4. Retry on failure: job fails first, succeeds on retry
    // ---------------------------------------------------------------
    #[Test]
    public function test_retry_on_failure_succeeds_on_second_attempt(): void
    {
        $job = new FailingThenSucceedingJob(jobKey: 'retry-test', failForAttempts: 1);
        $this->dispatcher->dispatch($job);

        // First worker run: job fails, gets re-queued for retry
        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 1));
        self::assertFalse(FailingThenSucceedingJob::$succeeded);
        self::assertSame(1, $this->driver->size('default'), 'Job should be re-queued for retry');

        // Second worker run: job succeeds
        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 1));
        self::assertTrue(FailingThenSucceedingJob::$succeeded);
        self::assertSame(0, $this->driver->size('default'));
        self::assertCount(0, $this->failedStore->all());
    }

    // ---------------------------------------------------------------
    // 5. Max retries exhausted: job always fails, ends in failed store
    // ---------------------------------------------------------------
    #[Test]
    public function test_max_retries_exhausted_moves_to_failed_store(): void
    {
        $job = new AlwaysFailingJob('persistent failure');
        $this->dispatcher->dispatch($job);

        // Process until retries are exhausted (maxAttempts=2)
        // Attempt 1: fail -> retry
        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 1));
        self::assertSame(1, AlwaysFailingJob::$handleCount);
        self::assertSame(1, $this->driver->size('default'), 'Should be re-queued after first failure');

        // Attempt 2: fail -> max reached -> failed store
        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 1));
        self::assertSame(2, AlwaysFailingJob::$handleCount);
        self::assertSame(0, $this->driver->size('default'), 'Should not be re-queued after max attempts');

        self::assertCount(1, $this->failedStore->all());
    }

    // ---------------------------------------------------------------
    // 6. Failed job stored with exception message
    // ---------------------------------------------------------------
    #[Test]
    public function test_failed_job_store_contains_exception_message(): void
    {
        $job = new AlwaysFailingJob('specific error message for test');
        // Create a SerializedJob already at max-1 attempts so next attempt exhausts retries
        $serialized = new SerializedJob(
            id: 'fail-test-' . bin2hex(random_bytes(4)),
            queue: 'default',
            payload: serialize($job),
            attempts: 1, // maxAttempts=2, so next attempt (2) will exhaust
            maxAttempts: 2,
            timeout: 60,
            availableAt: new \DateTimeImmutable(),
            createdAt: new \DateTimeImmutable(),
        );
        $this->driver->push('default', $serialized);

        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 1));

        $failedJobs = $this->failedStore->all();
        self::assertCount(1, $failedJobs);
        self::assertStringContainsString('specific error message for test', $failedJobs[0]->exception);
        self::assertSame('default', $failedJobs[0]->queue);
    }

    // ---------------------------------------------------------------
    // 7. Job with custom queue: only processed from correct queue
    // ---------------------------------------------------------------
    #[Test]
    public function test_job_dispatched_to_custom_queue_only_processed_from_that_queue(): void
    {
        $job = new EmailQueueJob();
        $this->dispatcher->dispatch($job);

        // Verify it went to the 'emails' queue
        self::assertSame(0, $this->driver->size('default'));
        self::assertSame(1, $this->driver->size('emails'));

        // Worker on 'default' queue should NOT process it
        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 10, sleep: 0));
        self::assertFalse(EmailQueueJob::$handled);
        self::assertSame(1, $this->driver->size('emails'));

        // Worker on 'emails' queue processes it
        $this->worker->work(new WorkerOptions(queue: 'emails', maxJobs: 1));
        self::assertTrue(EmailQueueJob::$handled);
        self::assertSame(0, $this->driver->size('emails'));
    }

    // ---------------------------------------------------------------
    // 8. Worker maxJobs: stops after processing N jobs
    // ---------------------------------------------------------------
    #[Test]
    public function test_worker_stops_after_max_jobs(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->dispatcher->dispatch(new CounterJob("job-{$i}"));
        }

        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 3));

        self::assertSame(3, CounterJob::$counter);
        self::assertSame(2, $this->driver->size('default'));
        self::assertCount(3, $this->driver->getAcknowledged());
    }

    // ---------------------------------------------------------------
    // 9. QueueFake: assertDispatched and assertNotDispatched
    // ---------------------------------------------------------------
    #[Test]
    public function test_queue_fake_assert_dispatched_and_not_dispatched(): void
    {
        $fake = new QueueFake();

        $fake->dispatch(new CounterJob('faked'));

        $fake->assertDispatched(CounterJob::class);
        $fake->assertNotDispatched(AlwaysFailingJob::class);
        $fake->assertDispatchedCount(CounterJob::class, 1);

        // Verify the job was NOT actually executed
        self::assertSame(0, CounterJob::$counter);
    }

    // ---------------------------------------------------------------
    // 10. dispatch() helper: global function queues the job
    // ---------------------------------------------------------------
    #[Test]
    public function test_global_dispatch_helper_queues_job(): void
    {
        // The dispatch() helper lives in packages/core/src/helpers.php.
        // Define it here if not already loaded, so the queue package test is self-contained.
        if (!function_exists('dispatch')) {
            require_once dirname(__DIR__, 3) . '/core/src/helpers.php';
        }

        // If the core helpers couldn't be loaded (e.g., missing dependency), test via facade directly
        if (!function_exists('dispatch')) {
            // Fallback: test the facade dispatch directly
            Queue::setInstance($this->dispatcher);
            $jobId = Queue::dispatch(new CounterJob('helper-test'));
        } else {
            // Set up the facade with our in-memory dispatcher
            Queue::setInstance($this->dispatcher);
            $jobId = dispatch(new CounterJob('helper-test'));
        }

        self::assertNotEmpty($jobId);
        self::assertSame(1, $this->driver->size('default'));

        // Process the job
        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 1));
        self::assertSame(1, CounterJob::$counter);
        self::assertSame(['helper-test'], CounterJob::$log);
    }

    // ---------------------------------------------------------------
    // 11. Job backoff: delays increase on retries based on backoff array
    // ---------------------------------------------------------------
    #[Test]
    public function test_backoff_delays_increase_on_retries(): void
    {
        $job = new BackoffTrackingJob();
        $retryPolicy = new RetryPolicy();

        // Simulate increasing attempts and verify delay values
        $serialized = SerializedJob::fromJob($job);

        // Attempt 1 (index 0) -> backoff[0] = 2
        $attempt1 = $serialized->withIncrementedAttempts(); // attempts=1
        self::assertTrue($retryPolicy->shouldRetry($attempt1));
        self::assertSame(2, $retryPolicy->getDelay($attempt1));

        // Attempt 2 (index 1) -> backoff[1] = 10
        $attempt2 = $attempt1->withIncrementedAttempts(); // attempts=2
        self::assertTrue($retryPolicy->shouldRetry($attempt2));
        self::assertSame(10, $retryPolicy->getDelay($attempt2));

        // Attempt 3 (index 2) -> backoff[2] = 60
        $attempt3 = $attempt2->withIncrementedAttempts(); // attempts=3
        self::assertTrue($retryPolicy->shouldRetry($attempt3));
        self::assertSame(60, $retryPolicy->getDelay($attempt3));

        // Attempt 4 -> max attempts (4) reached, should not retry
        $attempt4 = $attempt3->withIncrementedAttempts(); // attempts=4
        self::assertFalse($retryPolicy->shouldRetry($attempt4));
    }

    // ---------------------------------------------------------------
    // 12. Worker empty queue: stops gracefully
    // ---------------------------------------------------------------
    #[Test]
    public function test_worker_with_empty_queue_stops_gracefully(): void
    {
        // maxJobs > 0 with empty queue: should stop immediately
        $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 10, sleep: 0));

        // No exceptions, no infinite loop
        self::assertSame(0, CounterJob::$counter);
        self::assertCount(0, $this->driver->getAcknowledged());
    }

    // ---------------------------------------------------------------
    // 13. Multiple drivers: dispatcher uses correct driver per connection
    // ---------------------------------------------------------------
    #[Test]
    public function test_multiple_drivers_dispatch_to_correct_driver(): void
    {
        $syncDriver = new SyncDriver();
        $inMemoryDriver = new InMemoryDriver();

        $syncDispatcher = new Dispatcher($syncDriver);
        $asyncDispatcher = new Dispatcher($inMemoryDriver);

        // Sync driver executes immediately
        $syncDispatcher->dispatch(new CounterJob('sync'));
        self::assertSame(1, CounterJob::$counter);

        // In-memory driver stores for later
        $asyncDispatcher->dispatch(new CounterJob('async'));
        self::assertSame(1, CounterJob::$counter, 'In-memory dispatch should not execute immediately');
        self::assertSame(1, $inMemoryDriver->size('default'));

        // Process the async job
        $asyncWorker = new Worker($inMemoryDriver, $this->retryPolicy, $this->failedStore);
        $asyncWorker->work(new WorkerOptions(queue: 'default', maxJobs: 1));
        self::assertSame(2, CounterJob::$counter);
    }

    // ---------------------------------------------------------------
    // 14. FULL CYCLE: Job inserts into SQLite -> dispatch -> worker -> verify
    // ---------------------------------------------------------------
    #[Test]
    public function test_full_cycle_sqlite_insert_via_queue(): void
    {
        // Skip if pdo_sqlite is not available
        if (!extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('pdo_sqlite extension is required for this test.');
        }

        // Create a temporary SQLite database
        $dbPath = tempnam(sys_get_temp_dir(), 'lattice_queue_test_') . '.sqlite';
        $pdo = new \PDO('sqlite:' . $dbPath);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE job_results (key TEXT NOT NULL, value TEXT NOT NULL)');

        try {
            // Set the database path for the DatabaseJob fixture
            DatabaseJob::$dbPath = $dbPath;

            // Dispatch the job
            $job = new DatabaseJob(key: 'greeting', value: 'Hello from the queue!');
            $this->dispatcher->dispatch($job);

            // Verify job is queued, not executed
            $result = $pdo->query('SELECT COUNT(*) FROM job_results')->fetchColumn();
            self::assertSame('0', (string) $result, 'Row should not exist before worker processes');

            // Worker processes the job
            $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 1));

            // Verify the row was inserted
            $stmt = $pdo->query('SELECT key, value FROM job_results');
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            self::assertCount(1, $rows);
            self::assertSame('greeting', $rows[0]['key']);
            self::assertSame('Hello from the queue!', $rows[0]['value']);
        } finally {
            // Cleanup
            unset($pdo);
            if (file_exists($dbPath)) {
                @unlink($dbPath);
            }
        }
    }

    // ---------------------------------------------------------------
    // Bonus: Sync driver multiple dispatches
    // ---------------------------------------------------------------
    #[Test]
    public function test_sync_driver_executes_multiple_jobs_immediately(): void
    {
        $syncDriver = new SyncDriver();
        $syncDispatcher = new Dispatcher($syncDriver);

        $syncDispatcher->dispatch(new CounterJob('a'));
        $syncDispatcher->dispatch(new CounterJob('b'));
        $syncDispatcher->dispatch(new CounterJob('c'));

        self::assertSame(3, CounterJob::$counter);
        self::assertSame(['a', 'b', 'c'], CounterJob::$log);
    }

    // ---------------------------------------------------------------
    // Bonus: QueueFake tracks multiple dispatches
    // ---------------------------------------------------------------
    #[Test]
    public function test_queue_fake_tracks_multiple_dispatches(): void
    {
        $fake = new QueueFake();

        $fake->dispatch(new CounterJob('one'));
        $fake->dispatch(new CounterJob('two'));
        $fake->dispatch(new AlwaysFailingJob());

        $fake->assertDispatchedCount(CounterJob::class, 2);
        $fake->assertDispatchedCount(AlwaysFailingJob::class, 1);
        $fake->assertNotDispatched(EmailQueueJob::class);

        // Verify none actually executed
        self::assertSame(0, CounterJob::$counter);
        self::assertSame(0, AlwaysFailingJob::$handleCount);
    }

    // ---------------------------------------------------------------
    // Bonus: End-to-end retry flow with failed store verification
    // ---------------------------------------------------------------
    #[Test]
    public function test_end_to_end_retry_then_fail_stores_correctly(): void
    {
        // AlwaysFailingJob with maxAttempts=2
        $job = new AlwaysFailingJob('e2e failure');
        $this->dispatcher->dispatch($job);

        // Process all retries in a loop
        $maxIterations = 10; // safety limit
        $iterations = 0;
        while ($this->driver->size('default') > 0 && $iterations < $maxIterations) {
            $this->worker->work(new WorkerOptions(queue: 'default', maxJobs: 1));
            $iterations++;
        }

        // Should have been handled exactly maxAttempts times (2)
        self::assertSame(2, AlwaysFailingJob::$handleCount);

        // Should be in the failed store
        $failed = $this->failedStore->all();
        self::assertCount(1, $failed);
        self::assertStringContainsString('e2e failure', $failed[0]->exception);

        // Queue should be empty
        self::assertSame(0, $this->driver->size('default'));
    }
}
