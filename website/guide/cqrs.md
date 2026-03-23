---
outline: deep
---

# CQRS

LatticePHP provides a Command/Query Responsibility Segregation (CQRS) implementation with the `CommandBus` and `QueryBus`. These separate write operations (commands) from read operations (queries), each with their own middleware pipeline.

## Commands

Commands represent write operations -- things that change state. They are plain PHP objects:

```php
final readonly class CreateContact
{
    public function __construct(
        public string $name,
        public string $email,
        public string $createdBy,
    ) {}
}
```

### Command Handlers

Handle commands by creating a handler class:

```php
final class CreateContactHandler
{
    public function handle(CreateContact $command): Contact
    {
        return Contact::create([
            'name' => $command->name,
            'email' => $command->email,
            'owner_id' => $command->createdBy,
        ]);
    }
}
```

### Dispatching Commands

```php
use Lattice\Core\Bus\CommandBus;

final class ContactController
{
    public function __construct(
        private readonly CommandBus $bus,
    ) {}

    #[Post('/')]
    public function store(#[Body] CreateContactDto $dto, #[CurrentUser] Principal $user): Response
    {
        $contact = $this->bus->dispatch(new CreateContact(
            name: $dto->name,
            email: $dto->email,
            createdBy: $user->getId(),
        ));

        return ResponseFactory::created(['data' => $contact]);
    }
}
```

## Queries

Queries represent read operations -- they return data without modifying state:

```php
final readonly class GetContactById
{
    public function __construct(
        public int $id,
    ) {}
}

final class GetContactByIdHandler
{
    public function handle(GetContactById $query): ?Contact
    {
        return Contact::find($query->id);
    }
}
```

### Dispatching Queries

```php
use Lattice\Core\Bus\QueryBus;

#[Get('/:id')]
public function show(#[Param] int $id): Response
{
    $contact = $this->queryBus->dispatch(new GetContactById(id: $id));
    return ResponseFactory::json(['data' => $contact]);
}
```

## Bus Middleware

Both buses support middleware for cross-cutting concerns:

```php
$commandBus = new CommandBus([
    new LoggingMiddleware(),      // Log all commands
    new TransactionMiddleware(),  // Wrap in DB transaction
    new ValidationMiddleware(),   // Validate command
]);

$queryBus = new QueryBus([
    new LoggingMiddleware(),      // Log all queries
    new CacheMiddleware(),        // Cache query results
]);
```

## When to Use CQRS

Use CQRS when you need:
- Different models for reading and writing
- Separate scaling for read and write workloads
- Audit trails on all write operations
- Event sourcing with the workflow engine

For simpler applications, direct service calls are fine. CQRS adds value as complexity grows.

## Next Steps

- [Events & Listeners](events.md) -- dispatch events from command handlers
- [Workflows](workflows.md) -- durable execution with event sourcing
- [Pipeline](pipeline.md) -- interceptors and middleware
