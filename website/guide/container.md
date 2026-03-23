---
outline: deep
---

# Service Container

LatticePHP uses a dependency injection container backed by `illuminate/container`. The container resolves class dependencies automatically through constructor injection -- you declare what you need, and the framework provides it.

## Constructor Injection

The most common pattern. Declare dependencies as constructor parameters and the container resolves them:

```php
#[Controller('/api/contacts')]
final class ContactController
{
    public function __construct(
        private readonly ContactService $service,
    ) {}

    #[Post('/')]
    public function store(#[Body] CreateContactDto $dto): Response
    {
        $contact = $this->service->create($dto);
        return ResponseFactory::created(['data' => $contact]);
    }
}
```

The container sees that `ContactController` needs a `ContactService`, looks it up, and injects it. This works because `ContactService` is listed in the module's `providers` array.

## Registering Services

Services are registered through modules and service providers.

### Via Module Providers

List service classes in your module's `providers` array:

```php
#[Module(
    providers: [ContactService::class, ContactRepository::class],
    controllers: [ContactController::class],
    exports: [ContactService::class],
)]
final class ContactsModule {}
```

The container auto-resolves concrete classes. If `ContactService` has constructor dependencies, they are resolved recursively.

### Via Service Providers

For complex binding logic, create a `ServiceProvider`:

```php
final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Bind an interface to a concrete implementation
        $this->app->getContainer()->singleton(
            CacheInterface::class,
            RedisCacheDriver::class,
        );

        // Bind with a factory closure
        $this->app->getContainer()->singleton(
            PaymentGateway::class,
            fn () => new StripeGateway(env('STRIPE_KEY')),
        );
    }

    public function boot(): void
    {
        // Runs after ALL providers are registered
        // Safe to use other services here
    }
}
```

Register the provider in your module:

```php
#[Module(
    providers: [AppServiceProvider::class],
)]
final class AppModule {}
```

## Binding Types

### Singleton

A single instance is created and reused for every resolution:

```php
$container->singleton(CacheInterface::class, RedisCacheDriver::class);

// Both resolve to the SAME instance
$a = $container->make(CacheInterface::class);
$b = $container->make(CacheInterface::class);
// $a === $b
```

### Instance

Bind an already-created object:

```php
$config = new JwtConfig(secret: 'my-secret', algorithm: 'HS256');
$container->instance(JwtConfig::class, $config);
```

### Factory

Bind a closure that creates the instance:

```php
$container->singleton(DatabaseConnection::class, function () {
    return new PdoConnection(dsn: env('DB_DSN'));
});
```

### Interface to Implementation

The most common pattern for testability:

```php
// In production
$container->singleton(MailTransportInterface::class, SmtpTransport::class);

// In tests
$container->singleton(MailTransportInterface::class, InMemoryTransport::class);
```

## Module Scope and Exports

Modules control what services are visible to other modules through `exports`:

```php
#[Module(
    providers: [ContactService::class, ContactRepository::class],
    exports: [ContactService::class],   // Only ContactService is shared
)]
final class ContactsModule {}
```

`ContactRepository` stays private to `ContactsModule`. Only `ContactService` is injectable in modules that import `ContactsModule`.

::: tip
This explicit export system prevents accidental coupling between modules. If module A needs something from module B, it must import B, and B must export it.
:::

## The Provider Lifecycle

1. **Discovery** -- The framework reads `#[Module]` attributes and walks the import tree
2. **Register** -- `register()` is called on every provider across all modules
3. **Boot** -- `boot()` is called on every provider (all bindings are available)
4. **Resolve** -- When a class is requested, the container resolves its dependencies recursively

::: warning
Never resolve services inside `register()`. Other providers may not have registered yet. Use `boot()` for logic that depends on other services.
:::

## Testing

In tests, replace real services with fakes:

```php
final class ContactApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Replace the event bus with a fake
        $this->app->getContainer()->instance(
            EventBusInterface::class,
            new FakeEventBus(),
        );
    }

    public function test_create_contact_dispatches_event(): void
    {
        $this->postJson('/api/contacts', ['name' => 'Alice', 'email' => 'alice@test.com']);

        $events = $this->app->getContainer()->make(EventBusInterface::class);
        $events->assertDispatched(ContactCreatedEvent::class);
    }
}
```

## Next Steps

- [Modules](modules.md) -- how modules organize providers, controllers, and exports
- [Testing](testing.md) -- faking services and dependencies in tests
- [Architecture](architecture.md) -- the 4-layer architecture and boot sequence
