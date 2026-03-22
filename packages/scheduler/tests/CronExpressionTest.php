<?php

declare(strict_types=1);

namespace Lattice\Scheduler\Tests;

use Lattice\Scheduler\CronExpression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CronExpressionTest extends TestCase
{
    #[Test]
    public function it_parses_every_minute(): void
    {
        $cron = CronExpression::parse('* * * * *');

        $now = new \DateTimeImmutable('2026-03-21 14:30:00');
        $this->assertTrue($cron->isDue($now));
    }

    #[Test]
    public function it_parses_specific_minute(): void
    {
        $cron = CronExpression::parse('30 * * * *');

        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 14:30:00')));
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-03-21 14:31:00')));
    }

    #[Test]
    public function it_parses_specific_hour(): void
    {
        $cron = CronExpression::parse('0 14 * * *');

        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 14:00:00')));
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-03-21 15:00:00')));
    }

    #[Test]
    public function it_parses_specific_day_of_month(): void
    {
        $cron = CronExpression::parse('0 0 21 * *');

        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 00:00:00')));
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-03-22 00:00:00')));
    }

    #[Test]
    public function it_parses_specific_month(): void
    {
        $cron = CronExpression::parse('0 0 1 3 *');

        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-01 00:00:00')));
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-04-01 00:00:00')));
    }

    #[Test]
    public function it_parses_specific_day_of_week(): void
    {
        // 2026-03-21 is a Saturday (6)
        $cron = CronExpression::parse('0 0 * * 6');

        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 00:00:00')));
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-03-22 00:00:00')));
    }

    #[Test]
    public function it_parses_step_values(): void
    {
        $cron = CronExpression::parse('*/5 * * * *');

        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 14:00:00')));
        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 14:05:00')));
        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 14:10:00')));
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-03-21 14:03:00')));
    }

    #[Test]
    public function it_parses_range_values(): void
    {
        $cron = CronExpression::parse('0 9-17 * * *');

        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 09:00:00')));
        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 12:00:00')));
        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 17:00:00')));
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-03-21 08:00:00')));
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-03-21 18:00:00')));
    }

    #[Test]
    public function it_parses_list_values(): void
    {
        $cron = CronExpression::parse('0 9,12,17 * * *');

        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 09:00:00')));
        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 12:00:00')));
        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-21 17:00:00')));
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-03-21 10:00:00')));
    }

    #[Test]
    public function it_handles_sunday_as_0_and_7(): void
    {
        // 2026-03-22 is a Sunday
        $cron0 = CronExpression::parse('0 0 * * 0');
        $cron7 = CronExpression::parse('0 0 * * 7');

        $sunday = new \DateTimeImmutable('2026-03-22 00:00:00');
        $this->assertTrue($cron0->isDue($sunday));
        $this->assertTrue($cron7->isDue($sunday));
    }

    #[Test]
    public function it_throws_on_invalid_expression(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CronExpression::parse('invalid');
    }

    #[Test]
    public function it_throws_on_too_few_parts(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        CronExpression::parse('* * *');
    }

    #[Test]
    public function it_combines_multiple_fields(): void
    {
        // Every 15 minutes during business hours on weekdays
        $cron = CronExpression::parse('*/15 9-17 * * 1-5');

        // Saturday at 10:00 - not due (wrong day)
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-03-21 10:00:00'))); // Saturday

        // Monday at 10:15 - due
        $this->assertTrue($cron->isDue(new \DateTimeImmutable('2026-03-23 10:15:00'))); // Monday

        // Monday at 10:07 - not due (wrong minute)
        $this->assertFalse($cron->isDue(new \DateTimeImmutable('2026-03-23 10:07:00')));
    }
}
