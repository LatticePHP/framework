# Durable Workflows

LatticePHP includes a native durable execution engine inspired by Temporal. It provides deterministic replay, event sourcing, compensation/saga, signals, queries, and timers -- all without requiring an external Temporal service. The engine runs on your existing database and queue infrastructure.

---

## Core Concepts

A **workflow** is a class annotated with `#[Workflow]` that orchestrates a sequence of **activities** through a `WorkflowContext`. Every side effect (activity execution, timer, child workflow) is recorded as an event. If the process crashes mid-execution, the workflow replays its event history to reconstruct state and resume from where it left off.

---

## Defining a Workflow

```php
<?php

declare(strict_types=1);

namespace App\Workflows;

use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Attributes\QueryMethod;
use Lattice\Workflow\Attributes\SignalMethod;
use Lattice\Workflow\Runtime\WorkflowContext;

#[Workflow(name: 'OrderFulfillment', taskQueue: 'default')]
final class OrderFulfillmentWorkflow
{
    private string $status = 'pending';

    public function execute(WorkflowContext $ctx, array $input): array
    {
        $paymentResult = $ctx->executeActivity(
            PaymentActivity::class,
            'charge',
            $input['amount'],
        );
        $this->status = 'charged';

        $shipResult = $ctx->executeActivity(
            ShippingActivity::class,
            'ship',
            $input['address'],
        );
        $this->status = 'shipped';

        return ['payment' => $paymentResult, 'shipping' => $shipResult];
    }

    #[SignalMethod]
    public function markDelivered(): void
    {
        $this->status = 'delivered';
    }

    #[QueryMethod]
    public function getStatus(): string
    {
        return $this->status;
    }
}
```

The `#[Workflow]` attribute accepts an optional `name` (defaults to the class name) and `taskQueue` (defaults to `'default'`).

---

## Defining Activities

Activities perform side effects. They are normal classes annotated with `#[Activity]`.

```php
<?php

declare(strict_types=1);

namespace App\Workflows;

use Lattice\Workflow\Attributes\Activity;

#[Activity(name: 'PaymentActivity')]
final class PaymentActivity
{
    public function charge(float $amount): string
    {
        // Call payment gateway, return charge ID
        return 'charge_' . md5((string) $amount);
    }

    public function refund(string $chargeId): string
    {
        return 'refunded_' . $chargeId;
    }
}
```

Activities are called via `$ctx->executeActivity(ActivityClass::class, 'methodName', ...$args)`. Each invocation is recorded in the event store, so on replay the result is returned from history rather than re-executing.

---

## Signals and Queries

**Signals** send data into a running workflow. **Queries** read state without mutating it.

```php
// Send a signal
$handle->signal('markDelivered');

// Query current state
$status = $handle->query('getStatus');
```

Signal methods are annotated with `#[SignalMethod(name: 'increment')]` and query methods with `#[QueryMethod(name: 'getStatus')]`. If `name` is omitted, the method name is used.

---

## WorkflowContext API

`Lattice\Workflow\Runtime\WorkflowContext` is the primary interface inside workflow code.

| Method | Purpose |
|---|---|
| `executeActivity(string $class, string $method, mixed ...$args): mixed` | Execute an activity. Returns recorded result during replay. |
| `sleep(int $seconds): void` | Pause the workflow. Recorded as timer events. |
| `awaitCondition(callable $condition, int $timeoutSeconds): bool` | Wait until a condition is true. |
| `executeChildWorkflow(string $workflowClass, mixed $input): mixed` | Start and await a child workflow. |
| `getWorkflowId(): string` | Get the workflow ID. |
| `getRunId(): string` | Get the current run ID. |
| `isReplaying(): bool` | Check if replaying history. |

---

## Deterministic Replay

The engine replays event history to rebuild workflow state. Workflow code must be **deterministic**: no `rand()`, no `time()`, no direct I/O. All non-deterministic operations must go through activities.

During replay, `executeActivity()` returns the previously recorded result. When replay catches up to the end of history, the engine switches to live execution and records new events. If history diverges from the code (a non-deterministic change), a `ReplayCaughtUpException` is thrown.

---

## Compensation / Saga Pattern

Use `CompensationScope` to register undo actions that run in reverse order on failure.

```php
use Lattice\Workflow\Attributes\Workflow;
use Lattice\Workflow\Compensation\CompensationScope;
use Lattice\Workflow\Runtime\WorkflowContext;

#[Workflow]
final class BookingWorkflow
{
    public function execute(WorkflowContext $ctx, array $input): array
    {
        $scope = new CompensationScope();
        $results = [];

        $results['payment'] = $scope->run(
            fn () => $ctx->executeActivity(PaymentActivity::class, 'charge', $input['amount']),
            fn () => $ctx->executeActivity(PaymentActivity::class, 'refund', $results['payment'] ?? ''),
        );

        try {
            $results['shipping'] = $scope->run(
                fn () => $ctx->executeActivity(ShippingActivity::class, 'ship', $input['address']),
                fn () => $ctx->executeActivity(ShippingActivity::class, 'cancel', $results['shipping'] ?? ''),
            );
        } catch (\Throwable $e) {
            $scope->compensate(); // Runs refund (reverse order)
            throw $e;
        }

        return $results;
    }
}
```

`CompensationScope::run()` executes the action; if it succeeds, the compensation is registered. Calling `$scope->compensate()` runs all registered compensations in **reverse order**. If compensations fail, a `CompensationException` is thrown with all collected errors.

---

## Starting and Managing Workflows

Use `WorkflowClient` to start workflows and get handles.

```php
use Lattice\Workflow\Client\WorkflowClient;
use Lattice\Workflow\WorkflowOptions;

// Start a workflow
$handle = $client->start(
    OrderFulfillmentWorkflow::class,
    ['amount' => 99.99, 'address' => '123 Main St'],
    new WorkflowOptions(workflowId: 'order-123'),
);

// Get the result
$result = $handle->getResult();

// Get status
$status = $handle->getStatus(); // WorkflowStatus::Completed

// Get a handle for an existing workflow
$existing = $client->getHandle('order-123');

// Cancel or terminate
$handle->cancel();
$handle->terminate('no longer needed');
```

---

## Event Stores

All events are persisted in an event store implementing `WorkflowEventStoreInterface`.

**InMemoryEventStore** -- for testing:

```php
use Lattice\Workflow\Store\InMemoryEventStore;

$store = new InMemoryEventStore();
$executionId = $store->createExecution('OrderWorkflow', 'order-1', 'run-abc', $input);
$store->appendEvent($executionId, $event);
$events = $store->getEvents($executionId);
$execution = $store->findExecutionByWorkflowId('order-1');
```

For production, use `DatabaseEventStore` from the `workflow-store` package, which persists events to your database.

---

## Testing Workflows

`WorkflowTestEnvironment` provides a self-contained harness with `InMemoryEventStore` and synchronous execution.

```php
use Lattice\Workflow\Testing\WorkflowTestEnvironment;
use Lattice\Workflow\WorkflowOptions;

final class OrderWorkflowTest extends \PHPUnit\Framework\TestCase
{
    public function test_order_completes(): void
    {
        $env = new WorkflowTestEnvironment();
        $env->registerWorkflow(OrderFulfillmentWorkflow::class);
        $env->registerActivity(PaymentActivity::class);
        $env->registerActivity(ShippingActivity::class);

        $handle = $env->startWorkflow(
            OrderFulfillmentWorkflow::class,
            ['amount' => 50.0, 'address' => '456 Oak Ave'],
        );

        $env->assertWorkflowStarted(OrderFulfillmentWorkflow::class);
        $this->assertArrayHasKey('payment', $handle->getResult());
    }

    public function test_with_stubbed_activity(): void
    {
        $env = new WorkflowTestEnvironment();
        $env->registerWorkflow(OrderFulfillmentWorkflow::class);

        $stub = new class {
            public function charge(float $amount): string { return 'stub_charge'; }
        };
        $env->registerActivityInstance(PaymentActivity::class, $stub);
        $env->registerActivity(ShippingActivity::class);

        $handle = $env->startWorkflow(OrderFulfillmentWorkflow::class, ['amount' => 10.0, 'address' => 'Test']);
        $this->assertSame('stub_charge', $handle->getResult()['payment']);
    }

    public function test_signal_and_query(): void
    {
        $env = new WorkflowTestEnvironment();
        $env->registerWorkflow(OrderFulfillmentWorkflow::class);
        $env->registerActivity(PaymentActivity::class);
        $env->registerActivity(ShippingActivity::class);

        $options = new WorkflowOptions(workflowId: 'sig-test');
        $handle = $env->startWorkflow(
            OrderFulfillmentWorkflow::class,
            ['amount' => 25.0, 'address' => 'Test St'],
            $options,
        );

        $env->signalWorkflow('sig-test', 'markDelivered');
        $env->assertSignalSent('sig-test', 'markDelivered');
        $this->assertSame('delivered', $handle->query('getStatus'));
    }
}
```

| Method | Purpose |
|---|---|
| `registerWorkflow(string $class)` | Register a workflow class |
| `registerActivity(string $class)` | Register an activity class |
| `registerActivityInstance(string $class, object $instance)` | Register a mock/stub |
| `startWorkflow(string $type, mixed $input, ?options)` | Start a workflow |
| `signalWorkflow(string $workflowId, string $signal, mixed $payload)` | Send a signal |
| `assertWorkflowStarted(string $type)` | Assert a workflow was started |
| `assertSignalSent(string $workflowId, string $signal)` | Assert a signal was sent |

---

## Architecture

The workflow engine is built on three layers the application already has:

1. **Database** -- event history persistence (`InMemoryEventStore` or `DatabaseEventStore`)
2. **Queue** -- asynchronous activity execution
3. **Scheduler** -- timer management

No external Temporal server, no gRPC sidecar, no additional infrastructure.
