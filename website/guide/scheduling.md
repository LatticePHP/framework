---
outline: deep
---

# Task Scheduling

LatticePHP provides cron-based task scheduling through the `lattice/scheduler` package. Define scheduled tasks with cron expressions, and the framework runs them when due.

## Defining Scheduled Tasks

Register tasks in a `Schedule`:

```php
use Lattice\Scheduler\Schedule;

$schedule = new Schedule();

// Run every minute
$schedule->call(function () {
    // Clean up expired tokens
    RefreshToken::where('expires_at', '<', now())->delete();
})->everyMinute();

// Run daily at midnight
$schedule->call(function () {
    // Generate daily report
    $this->reportService->generateDaily();
})->dailyAt('00:00');

// Run with a cron expression
$schedule->call(function () {
    // Weekly cleanup
    AuditLog::where('created_at', '<', now()->subDays(90))->delete();
})->cron('0 2 * * 0'); // Sundays at 2 AM
```

## Running the Scheduler

Add this single cron entry to your system:

```
* * * * * cd /path-to-your-project && php bin/lattice schedule:run >> /dev/null 2>&1
```

The `schedule:run` command checks all registered tasks and executes any that are due.

## Cron Expressions

| Expression | Meaning |
|---|---|
| `* * * * *` | Every minute |
| `*/5 * * * *` | Every 5 minutes |
| `0 * * * *` | Every hour |
| `0 0 * * *` | Daily at midnight |
| `0 2 * * 0` | Weekly on Sunday at 2 AM |
| `0 0 1 * *` | Monthly on the 1st at midnight |

## Schedule Locking

The `ScheduleLock` prevents overlapping executions when running multiple servers:

```php
$schedule->call(function () {
    // Long-running task
})->dailyAt('03:00')->withoutOverlapping();
```

Uses `InMemoryScheduleLock` by default. For multi-server deployments, use a Redis-backed lock.

## Next Steps

- [Queues & Jobs](queues.md) -- background job processing
- [CLI Commands](cli.md) -- the `schedule:run` command
- [Deployment](deployment.md) -- cron setup in production
