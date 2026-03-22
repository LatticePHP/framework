<?php

declare(strict_types=1);

namespace Lattice\Scheduler\Tests\Unit;

use Lattice\Scheduler\InMemoryScheduleLock;
use Lattice\Scheduler\Schedule;
use Lattice\Scheduler\ScheduleRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScheduleRunTest extends TestCase
{
    #[Test]
    public function test_runs_due_callable_tasks(): void
    {
        $schedule = new Schedule();
        $executed = false;

        $schedule->call(function () use (&$executed): string {
            $executed = true;

            return 'done';
        })->everyMinute();

        $runner = new ScheduleRunner(new InMemoryScheduleLock());
        $now = new \DateTimeImmutable('2024-06-15 12:00:00');

        $results = $runner->run($schedule, $now);

        self::assertTrue($executed);
        self::assertCount(1, $results);
        self::assertTrue($results[0]['success']);
        self::assertSame('done', $results[0]['result']);
    }

    #[Test]
    public function test_skips_not_due_tasks(): void
    {
        $schedule = new Schedule();
        $executed = false;

        // Daily at midnight — only due at 00:00
        $schedule->call(function () use (&$executed): void {
            $executed = true;
        })->dailyAt('00:00');

        $runner = new ScheduleRunner(new InMemoryScheduleLock());
        // Not midnight
        $now = new \DateTimeImmutable('2024-06-15 14:30:00');

        $results = $runner->run($schedule, $now);

        self::assertFalse($executed);
        self::assertCount(0, $results);
    }

    #[Test]
    public function test_captures_task_failure(): void
    {
        $schedule = new Schedule();

        $schedule->call(function (): void {
            throw new \RuntimeException('Something broke');
        })->everyMinute();

        $runner = new ScheduleRunner(new InMemoryScheduleLock());
        $now = new \DateTimeImmutable('2024-06-15 12:00:00');

        $results = $runner->run($schedule, $now);

        self::assertCount(1, $results);
        self::assertFalse($results[0]['success']);
        self::assertSame('Something broke', $results[0]['error']);
    }

    #[Test]
    public function test_runs_multiple_due_tasks(): void
    {
        $schedule = new Schedule();
        $count = 0;

        $schedule->call(function () use (&$count): void {
            $count++;
        })->everyMinute();

        $schedule->call(function () use (&$count): void {
            $count++;
        })->everyMinute();

        $schedule->call(function () use (&$count): void {
            $count++;
        })->everyMinute();

        $runner = new ScheduleRunner(new InMemoryScheduleLock());
        $now = new \DateTimeImmutable('2024-06-15 12:00:00');

        $results = $runner->run($schedule, $now);

        self::assertSame(3, $count);
        self::assertCount(3, $results);
    }

    #[Test]
    public function test_without_overlapping_prevents_concurrent_execution(): void
    {
        $lock = new InMemoryScheduleLock();
        $schedule = new Schedule();

        $schedule->call(function (): string {
            return 'ran';
        })->everyMinute()->withoutOverlapping();

        $runner = new ScheduleRunner($lock);
        $now = new \DateTimeImmutable('2024-06-15 12:00:00');

        // First run succeeds
        $results1 = $runner->run($schedule, $now);
        self::assertCount(1, $results1);
        self::assertTrue($results1[0]['success']);

        // Second run should be skipped because lock is held
        $results2 = $runner->run($schedule, $now);
        self::assertCount(0, $results2);
    }

    #[Test]
    public function test_returns_empty_for_empty_schedule(): void
    {
        $schedule = new Schedule();
        $runner = new ScheduleRunner(new InMemoryScheduleLock());
        $now = new \DateTimeImmutable('2024-06-15 12:00:00');

        $results = $runner->run($schedule, $now);

        self::assertCount(0, $results);
    }
}
