<?php

declare(strict_types=1);

namespace Lattice\Scheduler\Tests;

use Lattice\Scheduler\InMemoryScheduleLock;
use Lattice\Scheduler\Schedule;
use Lattice\Scheduler\ScheduleRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScheduleRunnerTest extends TestCase
{
    #[Test]
    public function it_runs_due_tasks(): void
    {
        $schedule = new Schedule();
        $executed = false;

        $schedule->call(function () use (&$executed): string {
            $executed = true;
            return 'done';
        })->everyMinute();

        $runner = new ScheduleRunner(new InMemoryScheduleLock());
        $results = $runner->run($schedule, new \DateTimeImmutable('2026-03-21 14:30:00'));

        $this->assertTrue($executed);
        $this->assertCount(1, $results);
        $this->assertSame('done', $results[0]['result']);
        $this->assertTrue($results[0]['success']);
    }

    #[Test]
    public function it_skips_tasks_that_are_not_due(): void
    {
        $schedule = new Schedule();
        $executed = false;

        $schedule->call(function () use (&$executed): void {
            $executed = true;
        })->dailyAt('08:00');

        $runner = new ScheduleRunner(new InMemoryScheduleLock());
        $results = $runner->run($schedule, new \DateTimeImmutable('2026-03-21 14:30:00'));

        $this->assertFalse($executed);
        $this->assertCount(0, $results);
    }

    #[Test]
    public function it_prevents_overlapping_for_marked_tasks(): void
    {
        $lock = new InMemoryScheduleLock();
        $schedule = new Schedule();
        $count = 0;

        $schedule->call(function () use (&$count): void {
            $count++;
        })->everyMinute()->withoutOverlapping();

        $runner = new ScheduleRunner($lock);

        // First run should execute
        $results = $runner->run($schedule, new \DateTimeImmutable('2026-03-21 14:30:00'));
        $this->assertCount(1, $results);
        $this->assertSame(1, $count);

        // Second run should be skipped (lock is held)
        $results = $runner->run($schedule, new \DateTimeImmutable('2026-03-21 14:31:00'));
        $this->assertCount(0, $results);
        $this->assertSame(1, $count);
    }

    #[Test]
    public function it_allows_overlapping_by_default(): void
    {
        $lock = new InMemoryScheduleLock();
        $schedule = new Schedule();
        $count = 0;

        $schedule->call(function () use (&$count): void {
            $count++;
        })->everyMinute();

        $runner = new ScheduleRunner($lock);

        $runner->run($schedule, new \DateTimeImmutable('2026-03-21 14:30:00'));
        $runner->run($schedule, new \DateTimeImmutable('2026-03-21 14:31:00'));

        $this->assertSame(2, $count);
    }

    #[Test]
    public function it_catches_exceptions_from_tasks(): void
    {
        $schedule = new Schedule();

        $schedule->call(function (): void {
            throw new \RuntimeException('Task failed');
        })->everyMinute();

        $runner = new ScheduleRunner(new InMemoryScheduleLock());
        $results = $runner->run($schedule, new \DateTimeImmutable('2026-03-21 14:30:00'));

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertSame('Task failed', $results[0]['error']);
    }

    #[Test]
    public function it_runs_multiple_due_tasks(): void
    {
        $schedule = new Schedule();
        $results_data = [];

        $schedule->call(function () use (&$results_data): string {
            $results_data[] = 'task1';
            return 'one';
        })->everyMinute();

        $schedule->call(function () use (&$results_data): string {
            $results_data[] = 'task2';
            return 'two';
        })->everyMinute();

        $runner = new ScheduleRunner(new InMemoryScheduleLock());
        $results = $runner->run($schedule, new \DateTimeImmutable('2026-03-21 14:30:00'));

        $this->assertCount(2, $results);
        $this->assertSame(['task1', 'task2'], $results_data);
    }

    #[Test]
    public function it_releases_lock_after_non_overlapping_task_fails(): void
    {
        $lock = new InMemoryScheduleLock();
        $schedule = new Schedule();
        $attempt = 0;

        $schedule->call(function () use (&$attempt): string {
            $attempt++;
            if ($attempt === 1) {
                throw new \RuntimeException('First attempt fails');
            }
            return 'success';
        })->everyMinute()->withoutOverlapping();

        $runner = new ScheduleRunner($lock);

        // First run - fails but lock should be released
        $results = $runner->run($schedule, new \DateTimeImmutable('2026-03-21 14:30:00'));
        $this->assertFalse($results[0]['success']);

        // Second run - should be able to acquire lock again
        $results = $runner->run($schedule, new \DateTimeImmutable('2026-03-21 14:31:00'));
        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
    }
}
