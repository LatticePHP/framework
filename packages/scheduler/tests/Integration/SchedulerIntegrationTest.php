<?php

declare(strict_types=1);

namespace Lattice\Scheduler\Tests\Integration;

use DateTimeImmutable;
use Lattice\Scheduler\InMemoryScheduleLock;
use Lattice\Scheduler\Schedule;
use Lattice\Scheduler\ScheduleEntry;
use Lattice\Scheduler\ScheduleRunner;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class SchedulerIntegrationTest extends TestCase
{
    private Schedule $schedule;
    private ScheduleRunner $runner;
    private InMemoryScheduleLock $lock;

    protected function setUp(): void
    {
        $this->schedule = new Schedule();
        $this->lock = new InMemoryScheduleLock();
        $this->runner = new ScheduleRunner($this->lock);
        SchedulerIntegrationTest::$sideEffect = null;
    }

    /** Static var used by full-cycle test */
    public static ?string $sideEffect = null;

    #[Test]
    public function test_everyMinute_isDue_at_any_time(): void
    {
        $entry = ScheduleEntry::forCallable(fn (): bool => true, 'every-minute-task');
        $entry->everyMinute();

        // Any arbitrary time should be due
        $this->assertTrue($entry->isDue(new DateTimeImmutable('2026-03-22 14:37:00')));
        $this->assertTrue($entry->isDue(new DateTimeImmutable('2026-01-01 00:00:00')));
        $this->assertTrue($entry->isDue(new DateTimeImmutable('2026-12-31 23:59:00')));
    }

    #[Test]
    public function test_daily_isDue_only_at_midnight(): void
    {
        $entry = ScheduleEntry::forCallable(fn (): bool => true, 'daily-task');
        $entry->daily();

        // 00:00 should be due
        $this->assertTrue($entry->isDue(new DateTimeImmutable('2026-03-22 00:00:00')));

        // Any other time should NOT be due
        $this->assertFalse($entry->isDue(new DateTimeImmutable('2026-03-22 00:01:00')));
        $this->assertFalse($entry->isDue(new DateTimeImmutable('2026-03-22 12:00:00')));
        $this->assertFalse($entry->isDue(new DateTimeImmutable('2026-03-22 23:59:00')));
    }

    #[Test]
    public function test_cron_isDue_at_every_five_minutes(): void
    {
        $entry = ScheduleEntry::forCallable(fn (): bool => true, 'cron-task');
        $entry->cron('*/5 * * * *');

        // Should be due at :00, :05, :10, etc.
        $this->assertTrue($entry->isDue(new DateTimeImmutable('2026-03-22 10:00:00')));
        $this->assertTrue($entry->isDue(new DateTimeImmutable('2026-03-22 10:05:00')));
        $this->assertTrue($entry->isDue(new DateTimeImmutable('2026-03-22 10:10:00')));
        $this->assertTrue($entry->isDue(new DateTimeImmutable('2026-03-22 10:55:00')));

        // Should NOT be due at :01, :02, :03, :04, etc.
        $this->assertFalse($entry->isDue(new DateTimeImmutable('2026-03-22 10:01:00')));
        $this->assertFalse($entry->isDue(new DateTimeImmutable('2026-03-22 10:03:00')));
        $this->assertFalse($entry->isDue(new DateTimeImmutable('2026-03-22 10:07:00')));
    }

    #[Test]
    public function test_scheduleRunner_executes_only_due_tasks(): void
    {
        $dueExecuted = false;
        $notDueExecuted = false;

        // Task 1: everyMinute (always due)
        $this->schedule->call(function () use (&$dueExecuted): string {
            $dueExecuted = true;
            return 'due-result';
        })->everyMinute();

        // Task 2: daily (only due at midnight)
        $this->schedule->call(function () use (&$notDueExecuted): string {
            $notDueExecuted = true;
            return 'not-due-result';
        })->daily();

        // Run at 14:30 — only everyMinute should execute
        $now = new DateTimeImmutable('2026-03-22 14:30:00');
        $results = $this->runner->run($this->schedule, $now);

        $this->assertTrue($dueExecuted);
        $this->assertFalse($notDueExecuted);
        $this->assertCount(1, $results);
        $this->assertSame('callable:1', $results[0]['name']);
    }

    #[Test]
    public function test_scheduleRunner_captures_results_with_names_and_success(): void
    {
        $this->schedule->call(fn (): string => 'result-a')->everyMinute();
        $this->schedule->call(fn (): string => 'result-b')->everyMinute();

        $now = new DateTimeImmutable('2026-03-22 10:00:00');
        $results = $this->runner->run($this->schedule, $now);

        $this->assertCount(2, $results);

        // Both should be successful
        $this->assertTrue($results[0]['success']);
        $this->assertSame('result-a', $results[0]['result']);
        $this->assertSame('callable:1', $results[0]['name']);

        $this->assertTrue($results[1]['success']);
        $this->assertSame('result-b', $results[1]['result']);
        $this->assertSame('callable:2', $results[1]['name']);
    }

    #[Test]
    public function test_overlap_prevention_skips_when_lock_held(): void
    {
        $executionCount = 0;

        $this->schedule->call(function () use (&$executionCount): void {
            $executionCount++;
        })->everyMinute()->withoutOverlapping();

        $now = new DateTimeImmutable('2026-03-22 10:00:00');

        // First run: should execute and acquire lock
        $results1 = $this->runner->run($this->schedule, $now);
        $this->assertCount(1, $results1);
        $this->assertTrue($results1[0]['success']);
        $this->assertSame(1, $executionCount);

        // Second run: lock is already held, should skip
        $results2 = $this->runner->run($this->schedule, $now);
        $this->assertCount(0, $results2);
        $this->assertSame(1, $executionCount); // Still 1, not executed again
    }

    #[Test]
    public function test_task_failure_captured_in_results(): void
    {
        $this->schedule->call(function (): never {
            throw new \RuntimeException('Task exploded');
        })->everyMinute();

        $now = new DateTimeImmutable('2026-03-22 10:00:00');
        $results = $this->runner->run($this->schedule, $now);

        $this->assertCount(1, $results);
        $this->assertFalse($results[0]['success']);
        $this->assertSame('Task exploded', $results[0]['error']);
        $this->assertSame('callable:1', $results[0]['name']);
    }

    #[Test]
    public function test_schedule_registers_call_command_and_job(): void
    {
        $this->schedule->call(fn (): bool => true);
        $this->schedule->command('app:cleanup');
        $this->schedule->job('App\\Jobs\\SendReport');

        $entries = $this->schedule->getEntries();

        $this->assertCount(3, $entries);

        $this->assertSame('callable', $entries[0]->getType());
        $this->assertSame('callable:1', $entries[0]->getName());

        $this->assertSame('command', $entries[1]->getType());
        $this->assertSame('command:app:cleanup', $entries[1]->getName());

        $this->assertSame('job', $entries[2]->getType());
        $this->assertSame('job:App\\Jobs\\SendReport', $entries[2]->getName());
    }

    #[Test]
    public function test_full_cycle_task_writes_to_static_var(): void
    {
        $this->schedule->call(function (): void {
            SchedulerIntegrationTest::$sideEffect = 'task-executed';
        })->everyMinute();

        $this->assertNull(self::$sideEffect);

        $now = new DateTimeImmutable('2026-03-22 10:00:00');
        $results = $this->runner->run($this->schedule, $now);

        $this->assertCount(1, $results);
        $this->assertTrue($results[0]['success']);
        $this->assertSame('task-executed', self::$sideEffect);
    }
}
