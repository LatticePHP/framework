---
outline: deep
---

# Starter Kits

LatticePHP provides four starter templates, each designed for a specific architectural pattern. Pick the one that matches your use case.

## Quick Comparison

| Starter | Use Case | Protocol | Database | Key Attribute |
|---|---|---|---|---|
| **API** | REST API backends | HTTP/JSON | SQLite (default) | `#[Controller]`, `#[Get]`, `#[Post]` |
| **gRPC** | Typed service-to-service | gRPC / Protocol Buffers | PostgreSQL | `#[GrpcService]`, `#[GrpcMethod]` |
| **Service** | Event-driven microservices | NATS / RabbitMQ / SQS | PostgreSQL | `#[MessageController]`, `#[EventPattern]` |
| **Workflow** | Durable business processes | HTTP + Queue | PostgreSQL | `#[Workflow]`, `#[Activity]` |

## API Starter

The most complete starter. Use this for REST API backends, SaaS products, and general-purpose applications.

```bash
composer create-project lattice/starter-api my-app
```

### What's Included

- **12 configuration files** covering auth, database, cache, queue, mail, CORS, observability, workspaces, and tenancy
- **User model** with soft deletes and factory
- **Health endpoint** (`GET /health`)
- **User CRUD endpoints** with validated DTOs
- **Database migration** for users table
- **Seeder** that creates 1 admin + 5 users
- **PHPUnit test setup** with `RefreshDatabase`
- **Comprehensive `.env.example`** with 100+ documented variables

### Project Structure

```
my-app/
  app/
    Modules/App/AppModule.php       # Root module (imports AuthModule)
    Http/
      HealthController.php          # GET /health
      UserController.php            # CRUD: GET/POST/PUT/DELETE /users
    Dto/CreateUserDto.php           # Validated request DTO
    Models/User.php                 # Eloquent model
  config/                           # 12 config files
  database/migrations/              # Users table migration
  database/factories/               # User factory
  database/seeders/                 # Sample data seeder
  tests/                            # PHPUnit tests
```

### Bootstrap Pattern

```php
// bootstrap/app.php
// 1. Load .env
// 2. Boot Eloquent with database config
// 3. Build the application
return Application::configure(basePath: $basePath)
    ->withModules([AppModule::class])
    ->withHttp()
    ->create();
```

## gRPC Starter

For typed service-to-service communication using Protocol Buffers.

```bash
composer create-project lattice/starter-grpc my-app
```

### What's Included

- **Proto file** defining a `Greeter` service with unary and streaming RPCs
- **Service implementation** with `#[Injectable]` pattern
- **Module** with gRPC transport enabled

### Proto Definition

```protobuf
syntax = "proto3";
package greeter;

service Greeter {
    rpc SayHello (HelloRequest) returns (HelloReply);
    rpc SayHelloStream (HelloStreamRequest) returns (stream HelloReply);
}
```

### Service Implementation

```php
#[Injectable]
final class GreeterService
{
    public function sayHello(array $request): array
    {
        return ['message' => 'Hello, ' . ($request['name'] ?? 'World') . '!'];
    }

    public function sayHelloStream(array $request): iterable
    {
        $count = $request['count'] ?? 3;
        for ($i = 0; $i < $count; $i++) {
            yield ['message' => "Hello #{$i}: " . ($request['name'] ?? 'World')];
        }
    }
}
```

### Bootstrap Pattern

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withModules([AppModule::class])
    ->withGrpc()
    ->create();
```

## Service Starter

For event-driven microservices that communicate via message brokers.

```bash
composer create-project lattice/starter-service my-app
```

### What's Included

- **Message handler** with `#[EventPattern]` and `#[CommandPattern]` methods
- **Health and status** HTTP endpoints
- **NATS transport** configuration

### Message Handler

```php
#[MessageController]
final class OrderEventsHandler
{
    #[EventPattern('order.created')]
    public function handleOrderCreated(mixed $data): void
    {
        // Process new order event (fire-and-forget)
    }

    #[EventPattern('order.paid')]
    public function handleOrderPaid(mixed $data): void
    {
        // Process payment event
    }

    #[CommandPattern('order.cancel')]
    public function handleCancelOrder(mixed $data): void
    {
        // Handle cancel command (request-response)
    }
}
```

### Environment Setup

```bash
TRANSPORT_DRIVER=nats
TRANSPORT_HOST=127.0.0.1
TRANSPORT_PORT=4222
```

## Workflow Starter

For durable business processes that survive crashes and restarts.

```bash
composer create-project lattice/starter-workflow my-app
```

### What's Included

- **Workflow definition** with `#[Workflow]` attribute
- **Activity classes** with `#[Activity]` attribute
- **HTTP endpoints** for starting, querying, and cancelling workflows
- **Query and signal methods** for workflow inspection

### Workflow Definition

```php
#[Workflow(name: 'OrderFulfillment')]
final class OrderFulfillmentWorkflow
{
    private string $status = 'pending';

    public function execute(WorkflowContext $context): mixed
    {
        $this->status = 'processing_payment';
        $paymentResult = $context->executeActivity(
            PaymentActivity::class, 'processPayment',
        );

        $this->status = 'shipping';
        $shippingResult = $context->executeActivity(
            ShippingActivity::class, 'shipOrder',
        );

        $this->status = 'completed';
        return ['payment' => $paymentResult, 'shipping' => $shippingResult];
    }

    #[QueryMethod]
    public function getStatus(): string
    {
        return $this->status;
    }

    #[SignalMethod]
    public function cancel(): void
    {
        $this->status = 'cancelled';
    }
}
```

### HTTP Endpoints

```php
#[Controller('/workflows/order-fulfillment')]
final class WorkflowController
{
    #[Post('/')]
    public function start(): array
    {
        // Start workflow, return { workflowId, status }
    }

    #[Get('/:id')]
    public function status(#[Param] string $id): array
    {
        // Query workflow status
    }

    #[Post('/:id/cancel')]
    public function cancel(#[Param] string $id): array
    {
        // Signal cancellation
    }
}
```

## Choosing a Starter

**Start with the API starter** if you're not sure. It includes the complete framework stack and you can add gRPC, microservices, or workflows later by importing the relevant modules.

| Scenario | Recommended Starter |
|---|---|
| Building a SaaS product API | **API** |
| Internal service for an existing architecture | **Service** |
| High-performance inter-service communication | **gRPC** |
| Order processing, onboarding, payment flows | **Workflow** |
| Prototyping or learning LatticePHP | **API** |

## Next Steps

- [Your First API](getting-started.md) -- build a CRUD API step by step
- [Architecture](architecture.md) -- understand the module system
- [Workflows](workflows.md) -- durable execution deep dive
- [Microservices](microservices.md) -- transport-aware controllers
