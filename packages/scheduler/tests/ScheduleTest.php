<?php

declare(strict_types=1);

namespace Lattice\Scheduler\Tests;

use Lattice\Scheduler\Schedule;
use Lattice\Scheduler\ScheduleEntry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScheduleTest extends TestCase
{
    #[Test]
    public function it_registers_callable_tasks(): void
    {
        $schedule = new Schedule();
        $entry = $schedule->call(fn () => 'result');

        $this->assertInstanceOf(ScheduleEntry::class, $entry);
    }

    #[Test]
    public function it_registers_command_tasks(): void
    {
        $schedule = new Schedule();
        $entry = $schedule->command('cache:clear');

        $this->assertInstanceOf(ScheduleEntry::class, $entry);
        $this->assertSame('command:cache:clear', $entry->getName());
    }

    #[Test]
    public function it_registers_job_tasks(): void
    {
        $schedule = new Schedule();
        $entry = $schedule->job('App\\Jobs\\SendEmails');

        $this->assertInstanceOf(ScheduleEntry::class, $entry);
        $this->assertSame('job:App\\Jobs\\SendEmails', $entry->getName());
    }

    #[Test]
    public function it_returns_all_entries(): void
    {
        $schedule = new Schedule();
        $schedule->call(fn () => null)->everyMinute();
        $schedule->command('cache:clear')->daily();
        $schedule->job('App\\Jobs\\Send')->hourly();

        $entries = $schedule->getEntries();

        $this->assertCount(3, $entries);
    }

    #[Test]
    public function it_returns_due_entries(): void
    {
        $schedule = new Schedule();

        $schedule->call(fn () => 'every-minute')->everyMinute();
        $schedule->call(fn () => 'daily-at-noon')->dailyAt('12:00');

        $now = new \DateTimeImmutable('2026-03-21 12:00:00');
        $dueEntries = $schedule->getDueEntries($now);

        $this->assertCount(2, $dueEntries);

        $notNoon = new \DateTimeImmutable('2026-03-21 13:00:00');
        $dueEntries = $schedule->getDueEntries($notNoon);

        $this->assertCount(1, $dueEntries);
    }
}
