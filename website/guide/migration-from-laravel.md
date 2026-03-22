---
outline: deep
---

# Migration from Laravel

## Overview

LatticePHP uses Illuminate components under the hood, so many concepts will feel familiar. But the programming model is different: modules instead of service providers, guards instead of middleware, DTOs instead of form requests, and attribute-based routing instead of route files.

**Key principle:** LatticePHP is backend-only. No Blade, no frontend tooling, no SSR.

## Concept Mapping

| Laravel | LatticePHP | Effort |
|---|---|---|
| Service Provider | `#[Module]` class | Medium |
| `routes/api.php` | `#[Controller]` + `#[Get]`/`#[Post]` | Medium |
| Middleware | Guard / Interceptor / Pipe | Medium |
| Form Request | DTO with validation attributes | Medium |
| Eloquent Model | Eloquent Model | **None** |
| Migration | Migration | **None** |
| Factory | Factory | **None** |
| Controller | `#[Controller]` | Low |
| Policy | `#[Policy]` + `#[Can]` | Low |
| Event / Listener | `#[Listener]` attribute | Low |
| Job (ShouldQueue) | Job + `$queue->dispatch()` | Low |
| Facade | Constructor injection | Medium |
| `app()` helper | Constructor injection | Medium |
| Config / `.env` | Same | **None** |
| Blade / View | N/A | N/A |

## Step-by-Step Conversion

### Phase 1: Project Setup

```bash
# Create a new LatticePHP project
composer create-project lattice/starter-api my-app

# Copy these files directly (they work as-is):
cp laravel-app/.env            my-app/.env
cp laravel-app/config/*.php    my-app/config/
cp laravel-app/database/migrations/*.php  my-app/database/migrations/
cp laravel-app/database/factories/*.php   my-app/database/factories/
cp laravel-app/database/seeders/*.php     my-app/database/seeders/
```

### Phase 2: Plan Module Boundaries

Map each Laravel "domain" to a LatticePHP module:

```
app/
  Http/Controllers/UserController.php     -> app/Modules/Users/UserController.php
  Http/Requests/CreateUserRequest.php     -> app/Modules/Users/Dto/CreateUserDto.php
  Services/UserService.php                -> app/Modules/Users/UserService.php
  Models/User.php                         -> app/Models/User.php (unchanged)
  Providers/AppServiceProvider.php        -> app/AppModule.php
```

### Phase 3: Convert Routes to Attributes

**Laravel:**

```php
// routes/api.php
Route::middleware('auth:api')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
});
```

**LatticePHP:**

```php
#[Controller('/api/users')]
#[UseGuards(guards: [JwtAuthenticationGuard::class])]
final class UserController
{
    public function __construct(
        private readonly UserService $service,
    ) {}

    #[Get('/')]
    public function index(Request $request): Response
    {
        return ResponseFactory::paginated($this->service->list($request->query), UserResource::class);
    }

    #[Post('/')]
    public function store(#[Body] CreateUserDto $dto): Response
    {
        $user = $this->service->create($dto);
        return ResponseFactory::created(['data' => UserResource::make($user)->toArray()]);
    }

    #[Get('/:id')]
    public function show(#[Param] int $id): Response
    {
        $user = $this->service->findOrFail($id);
        return ResponseFactory::json(['data' => UserResource::make($user)->toArray()]);
    }
}
```

### Phase 4: Convert Form Requests to DTOs

**Laravel:**

```php
class CreateUserRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
        ];
    }
}
```

**LatticePHP:**

```php
final readonly class CreateUserDto
{
    public function __construct(
        #[Required]
        #[StringType(minLength: 1, maxLength: 100)]
        public string $name,

        #[Required]
        #[Email]
        #[Unique(table: 'users', column: 'email')]
        public string $email,

        #[Required]
        #[StringType(minLength: 8)]
        public string $password,
    ) {}
}
```

### Phase 5: Convert Middleware to Guards

**Laravel:**

```php
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403);
        }
        return $next($request);
    }
}
```

**LatticePHP:**

```php
final class AdminGuard implements Guard
{
    public function canActivate(ExecutionContext $context): bool
    {
        $user = $context->getUser();
        return $user?->hasRole('admin') ?? false;
    }
}

// On controller:
#[UseGuards(guards: [JwtAuthenticationGuard::class, AdminGuard::class])]
final class AdminController { ... }
```

### Phase 6: Convert Events/Listeners

**Laravel:**

```php
// EventServiceProvider
protected $listen = [
    OrderCreated::class => [SendOrderConfirmation::class],
];
```

**LatticePHP:**

```php
#[Listener(event: OrderCreated::class)]
final class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        $this->mailer->sendOrderConfirmation($event->orderId);
    }
}
```

No `EventServiceProvider` needed -- the `#[Listener]` attribute auto-registers.

## What Works the Same (Copy Directly)

| Component | Change Required |
|---|---|
| Eloquent models | Change base class to `Lattice\Database\Model` |
| Relationships | None |
| Scopes, accessors, mutators | None |
| Casts | None |
| Database migrations | Use `Capsule::schema()` instead of `Schema::` |
| Factories | Change base class to `Lattice\Database\Factory` |
| `.env` files | None |
| `config/*.php` files | None |
| Collections, `Str::`, `Arr::`, Carbon | None |

## What Is New (Not in Laravel)

### Module System

```php
#[Module(
    imports: [DatabaseModule::class, CacheModule::class],
    providers: [OrderService::class, OrderRepository::class],
    controllers: [OrderController::class],
    exports: [OrderService::class],
)]
final class OrdersModule {}
```

### Workspace / Multi-Tenancy

```php
final class Contact extends Model
{
    use BelongsToWorkspace; // Auto-scopes queries to current workspace
}
```

### Durable Workflow Engine

```php
#[Workflow('onboard-customer')]
final class OnboardCustomerWorkflow
{
    public function execute(WorkflowContext $ctx, int $customerId): void
    {
        $ctx->executeActivity(CreateAccount::class, 'run', $customerId);
        $ctx->executeActivity(SendWelcomeEmail::class, 'run', $customerId);
    }
}
```

### CQRS, Circuit Breaker, Feature Flags

All built-in with attribute-based configuration.

## Common Gotchas

1. **No implicit model binding.** Use `#[Param]` and look up manually.
2. **No global helpers in services.** Inject dependencies instead.
3. **Modules must export providers** for cross-module access.
4. **DTOs are readonly.** Create new DTOs to transform data.
5. **No Blade.** Frontend must be a separate SPA.
6. **Routes use `:param` not `{param}`.** Write `/:id` not `/{id}`.
7. **CLI is `php lattice`** not `php artisan`.
8. **Always `php lattice compile` in production** for zero reflection overhead.

## Migration Checklist

- [ ] Create new LatticePHP project
- [ ] Copy `.env`, `config/`, migrations, factories, seeders
- [ ] Plan module boundaries
- [ ] Create `AppModule` with imports
- [ ] Convert models (change base class, add `final`)
- [ ] Create DTOs for each Form Request
- [ ] Convert controllers (route attributes, inject services)
- [ ] Convert middleware to guards/interceptors
- [ ] Convert event listeners to `#[Listener]`
- [ ] Convert jobs
- [ ] Convert policies to `#[Policy]`
- [ ] Replace facades with constructor injection
- [ ] Run `php lattice compile` for production
- [ ] Run full test suite
