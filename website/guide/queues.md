---
outline: deep
---

# Queues & Jobs

LatticePHP provides a job queue for offloading time-consuming tasks -- sending emails, processing payments, generating reports -- from your HTTP request cycle to background workers.

## Defining Jobs

Jobs implement the `ShouldQueue` interface:

```php
<?php
declare(strict_types=1);

namespace App\Jobs;

use Lattice\Queue\ShouldQueue;

final class SendWelcomeEmail implements ShouldQueue
{
    public function __construct(
        private readonly int $userId,
        private readonly string $email,
    ) {}

    public function handle(): void
    {
        // Send the email
        $user = User::find($this->userId);
        Mail::to($this->email)->send('welcome', ['user' => $user]);
    }
}
```

## Dispatching Jobs

Use the `QueueDispatcher` to push jobs to the queue:

```php
final class UserService
{
    public function __construct(
        private readonly QueueDispatcher $queue,
    ) {}

    public function register(RegisterDto $dto): User
    {
        $user = User::create([...]);

        // Job runs in background worker, not current request
        $this->queue->dispatch(new SendWelcomeEmail(
            userId: $user->id,
            email: $user->email,
        ));

        return $user;
    }
}
```

## Queue Drivers

Configure the queue driver in `config/queue.php`:

| Driver | Use Case | Configuration |
|---|---|---|
| `sync` | Development -- jobs run immediately | `QUEUE_CONNECTION=sync` |
| `database` | Production without Redis | `QUEUE_CONNECTION=database` |
| `redis` | Production with Redis | `QUEUE_CONNECTION=redis` |

```php
// config/queue.php
return [
    'default' => env('QUEUE_CONNECTION', 'sync'),
    'connections' => [
        'sync'     => ['driver' => 'sync'],
        'database' => ['driver' => 'database', 'table' => 'jobs'],
        'redis'    => ['driver' => 'redis', 'connection' => 'default'],
    ],
];
```

::: tip
Use `sync` during development so jobs run immediately and errors show in your console. Switch to `database` or `redis` in production.
:::

## Running Workers

Start a queue worker to process jobs:

```bash
# Process jobs from the default queue
php bin/lattice queue:work

# Process specific queues
php bin/lattice queue:work --queue=emails,notifications

# Monitor queue depth and performance
php bin/lattice queue:monitor
```

::: warning
In production, use a process manager like Supervisor or Docker to keep workers running. Workers should be restarted after deployments to pick up code changes.
:::

## Retry Policies

Configure how failed jobs are retried:

```php
final class ProcessPayment implements ShouldQueue
{
    public int $tries = 3;          // Maximum attempts
    public int $retryAfter = 60;    // Seconds between retries

    public function handle(): void
    {
        // Process payment...
    }
}
```

## Failed Jobs

Jobs that exceed their retry count are stored in the failed jobs table:

```bash
# List failed jobs
php bin/lattice queue:failed

# Retry all failed jobs
php bin/lattice queue:failed --retry
```

Configure the failed job store in `config/queue.php`:

```php
'failed' => [
    'driver' => env('QUEUE_FAILED_DRIVER', 'database'),
    'table'  => 'failed_jobs',
],
```

## Testing

Use `FakeQueueDispatcher` to capture dispatched jobs without actually processing them:

```php
use Lattice\Testing\Fakes\FakeQueueDispatcher;

public function test_register_dispatches_welcome_email(): void
{
    $queue = new FakeQueueDispatcher();
    $this->app->getContainer()->instance(QueueDispatcherInterface::class, $queue);

    $this->postJson('/api/auth/register', [
        'name' => 'Alice',
        'email' => 'alice@test.com',
        'password' => 'secret123',
    ]);

    $queue->assertDispatched(SendWelcomeEmail::class);
    $queue->assertNotDispatched(ProcessPayment::class);
}
```

## Queue Monitoring with Loom

The [Loom](https://github.com/latticephp/loom) dashboard provides a web UI for monitoring queues in real-time:

- Queue depth and throughput metrics
- Recent and failed job inspection
- One-click retry for failed jobs
- Worker status and health
- SSE-powered live updates

## Next Steps

- [Events & Listeners](events.md) -- async event dispatch to queues
- [Task Scheduling](scheduling.md) -- cron-based job scheduling
- [Workflows](workflows.md) -- durable multi-step orchestration
- [Testing](testing.md) -- faking queue dispatches
