<?php

declare(strict_types=1);

namespace Lattice\Scheduler\Tests;

use Lattice\Scheduler\ScheduleEntry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScheduleEntryTest extends TestCase
{
    #[Test]
    public function it_creates_callable_entry(): void
    {
        $fn = fn () => 'done';
        $entry = ScheduleEntry::forCallable($fn, 'my-task');

        $this->assertSame('my-task', $entry->getName());
    }

    #[Test]
    public function it_creates_command_entry(): void
    {
        $entry = ScheduleEntry::forCommand('cache:clear');

        $this->assertSame('command:cache:clear', $entry->getName());
    }

    #[Test]
    public function it_creates_job_entry(): void
    {
        $entry = ScheduleEntry::forJob('App\\Jobs\\SendEmails');

        $this->assertSame('job:App\\Jobs\\SendEmails', $entry->getName());
    }

    #[Test]
    public function it_fluently_sets_every_minute(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->everyMinute();

        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 14:30:00')));
        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 14:31:00')));
    }

    #[Test]
    public function it_fluently_sets_every_five_minutes(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->everyFiveMinutes();

        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 14:00:00')));
        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 14:05:00')));
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-21 14:03:00')));
    }

    #[Test]
    public function it_fluently_sets_every_fifteen_minutes(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->everyFifteenMinutes();

        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 14:00:00')));
        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 14:15:00')));
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-21 14:07:00')));
    }

    #[Test]
    public function it_fluently_sets_every_thirty_minutes(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->everyThirtyMinutes();

        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 14:00:00')));
        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 14:30:00')));
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-21 14:15:00')));
    }

    #[Test]
    public function it_fluently_sets_hourly(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->hourly();

        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 14:00:00')));
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-21 14:30:00')));
    }

    #[Test]
    public function it_fluently_sets_daily(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->daily();

        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 00:00:00')));
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-21 01:00:00')));
    }

    #[Test]
    public function it_fluently_sets_daily_at(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->dailyAt('13:30');

        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 13:30:00')));
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-21 13:31:00')));
    }

    #[Test]
    public function it_fluently_sets_weekly(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->weekly();

        // Sunday at midnight (day 0)
        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-22 00:00:00'))); // Sunday
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-23 00:00:00'))); // Monday
    }

    #[Test]
    public function it_fluently_sets_weekly_on(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->weeklyOn(1, '08:00'); // Monday at 8am

        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-23 08:00:00'))); // Monday
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-23 09:00:00'))); // Monday wrong time
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-24 08:00:00'))); // Tuesday
    }

    #[Test]
    public function it_fluently_sets_monthly(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->monthly();

        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-01 00:00:00')));
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-02 00:00:00')));
    }

    #[Test]
    public function it_fluently_sets_cron_expression(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->cron('30 14 * * *');

        $this->assertTrue($entry->isDue(new \DateTimeImmutable('2026-03-21 14:30:00')));
        $this->assertFalse($entry->isDue(new \DateTimeImmutable('2026-03-21 14:31:00')));
    }

    #[Test]
    public function it_supports_without_overlapping(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->everyMinute()
            ->withoutOverlapping();

        $this->assertTrue($entry->isWithoutOverlapping());
    }

    #[Test]
    public function it_defaults_to_allowing_overlapping(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test')
            ->everyMinute();

        $this->assertFalse($entry->isWithoutOverlapping());
    }

    #[Test]
    public function it_returns_self_for_fluent_chaining(): void
    {
        $entry = ScheduleEntry::forCallable(fn () => null, 'test');

        $this->assertSame($entry, $entry->everyMinute());
        $this->assertSame($entry, $entry->withoutOverlapping());
    }
}
