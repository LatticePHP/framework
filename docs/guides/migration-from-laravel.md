# Migration from Laravel to LatticePHP

## Overview

LatticePHP uses Illuminate components under the hood, so many concepts will feel familiar. But the programming model is different: modules instead of service providers, guards instead of middleware, DTOs instead of form requests, and attribute-based routing instead of route files. This guide provides a complete, mechanical process for converting a Laravel app to LatticePHP.

**Key principle:** LatticePHP is backend-only. No Blade, no frontend tooling, no SSR.

---

## 1. Concept Mapping Table

| Laravel Concept | LatticePHP Equivalent | Migration Effort | Notes |
|---|---|---|---|
| Service Provider | `#[Module]` class | Medium | Modules declare imports, providers, controllers, exports |
| `routes/api.php` / `routes/web.php` | `#[Controller]` + `#[Get]`/`#[Post]`/`#[Put]`/`#[Delete]` | Medium | Routes declared on controller methods via attributes |
| Middleware | Guard (`#[UseGuards]`) / Interceptor / Pipe | Medium | Guards for auth, interceptors for before/after logic |
| Form Request | DTO with validation attributes | Medium | `readonly class` with `#[Required]`, `#[Email]`, etc. |
| Eloquent Model | Eloquent Model | **None** | Same `illuminate/database`, same relationships, same scopes |
| Migration | Migration | **None** | Same `Blueprint` API via `illuminate/database` |
| Factory | Factory | **None** | Same factory pattern, extends `Lattice\Database\Factory` |
| Seeder | Seeder | **None** | Same concept |
| Controller | Controller (`#[Controller]`) | Low | Thin controllers, inject services |
| Resource (API Resource) | Resource class | Low | Same `toArray()` pattern |
| Policy | `#[Policy]` + `#[Can]` | Low | Attribute-registered instead of `AuthServiceProvider` |
| Event | Event class | **None** | Same `illuminate/events` underneath |
| Listener | `#[Listener]` | Low | Registered via attribute instead of `EventServiceProvider` |
| Job (ShouldQueue) | Job class + `$queue->dispatch()` | Low | Same queue mechanics, different dispatch API |
| Notification | Activity / custom service | Medium | No built-in notification channels |
| Facade | Constructor injection | Medium | No facades; all dependencies injected explicitly |
| Config (`config/*.php`) | Config (`config/*.php`) | **None** | Same file format, same `config()` helper |
| `.env` | `.env` | **None** | Same `env()` helper, same `.env` files |
| `app()` helper | Constructor injection | Medium | No global `app()` — inject what you need |
| Route Model Binding | `#[Param]` + service lookup | Low | Explicit instead of implicit |
| Artisan Command | CLI Command | Low | `php lattice <cmd>` instead of `php artisan <cmd>` |
| Blade / View | N/A | N/A | LatticePHP is backend-only |
| Scheduler | `#[Schedule]` attribute | Low | Same cron expressions |

---

## 2. Step-by-Step Conversion Process

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
  Http/Controllers/UserController.php        -> app/Modules/Users/UserController.php
  Http/Controllers/OrderController.php       -> app/Modules/Orders/OrderController.php
  Http/Requests/CreateUserRequest.php        -> app/Modules/Users/Dto/CreateUserDto.php
  Http/Resources/UserResource.php            -> app/Modules/Users/UserResource.php
  Services/UserService.php                   -> app/Modules/Users/UserService.php
  Models/User.php                            -> app/Models/User.php (unchanged)
  Providers/AppServiceProvider.php           -> app/AppModule.php
  Providers/AuthServiceProvider.php          -> app/Modules/Auth/AuthModule.php
  Providers/EventServiceProvider.php         -> (listeners registered via attributes)
```

### Phase 3: Create Modules (Replace Service Providers)

### Phase 4: Convert Routes to Attributes

### Phase 5: Convert Middleware to Guards/Interceptors

### Phase 6: Convert Form Requests to DTOs

### Phase 7: Convert Events/Listeners

### Phase 8: Convert Jobs

### Phase 9: Verify and Test

---

## 3. Before/After Code Examples

### 3.1 Routes: `routes/api.php` to Controller Attributes

**Laravel:**

```php
// routes/api.php
use App\Http\Controllers\UserController;
use App\Http\Controllers\OrderController;

Route::middleware('auth:api')->group(function () {
    Route::get('/users', [UserController::class, 'index']);
    Route::post('/users', [UserController::class, 'store']);
    Route::get('/users/{id}', [UserController::class, 'show']);
    Route::put('/users/{id}', [UserController::class, 'update']);
    Route::delete('/users/{id}', [UserController::class, 'destroy']);

    Route::get('/users/{id}/orders', [OrderController::class, 'forUser']);
    Route::post('/orders', [OrderController::class, 'store']);
});
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Users;

use Lattice\Auth\JwtAuthenticationGuard;
use Lattice\Http\Request;
use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Pipeline\Attributes\UseGuards;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\Delete;
use Lattice\Routing\Attributes\Get;
use Lattice\Routing\Attributes\Param;
use Lattice\Routing\Attributes\Post;
use Lattice\Routing\Attributes\Put;

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
        $users = $this->service->list($request->query);
        return ResponseFactory::paginated($users, UserResource::class);
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

    #[Put('/:id')]
    public function update(#[Param] int $id, #[Body] UpdateUserDto $dto): Response
    {
        $user = $this->service->update($id, $dto);
        return ResponseFactory::json(['data' => UserResource::make($user)->toArray()]);
    }

    #[Delete('/:id')]
    public function destroy(#[Param] int $id): Response
    {
        $this->service->delete($id);
        return ResponseFactory::noContent();
    }
}
```

**Key differences:**
- No `routes/api.php` file at all
- Route verb + path declared as attributes on each method
- `{id}` becomes `/:id` with `#[Param]`
- Middleware `auth:api` becomes `#[UseGuards]`
- Controller must be registered in a module

---

### 3.2 Middleware to `#[UseGuards]`

**Laravel:**

```php
// app/Http/Middleware/EnsureUserIsAdmin.php
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isAdmin()) {
            abort(403, 'Admin access required.');
        }
        return $next($request);
    }
}

// In Kernel.php or route group:
Route::middleware(['auth:api', 'admin'])->group(function () { ... });
```

**LatticePHP:**

```php
// app/Guards/AdminGuard.php
<?php

declare(strict_types=1);

namespace App\Guards;

use Lattice\Pipeline\Guard;
use Lattice\Pipeline\ExecutionContext;

final class AdminGuard implements Guard
{
    public function canActivate(ExecutionContext $context): bool
    {
        $user = $context->getUser();
        return $user?->hasRole('admin') ?? false;
    }
}

// On the controller:
#[Controller('/api/admin')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, AdminGuard::class])]
final class AdminController { ... }
```

**For cross-cutting concerns (logging, timing)**, use Interceptors:

**Laravel:**

```php
class LogRequests
{
    public function handle(Request $request, Closure $next): Response
    {
        Log::info('Incoming', ['url' => $request->url(), 'method' => $request->method()]);
        $response = $next($request);
        Log::info('Outgoing', ['status' => $response->status()]);
        return $response;
    }
}
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

namespace App\Interceptors;

use Lattice\Pipeline\Interceptor;
use Lattice\Pipeline\ExecutionContext;
use Lattice\Pipeline\CallHandler;
use Lattice\Observability\Log;

final class LoggingInterceptor implements Interceptor
{
    public function intercept(ExecutionContext $context, CallHandler $next): mixed
    {
        Log::info('Incoming', ['handler' => $context->getHandler()]);
        $result = $next->handle();
        Log::info('Handled');
        return $result;
    }
}
```

---

### 3.3 Form Request to DTO with Validation Attributes

**Laravel:**

```php
// app/Http/Requests/CreateUserRequest.php
class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:100'],
            'email'    => ['required', 'email', 'unique:users'],
            'password' => ['required', 'string', 'min:8'],
            'role'     => ['sometimes', 'in:admin,user,editor'],
            'phone'    => ['nullable', 'string', 'max:30'],
            'bio'      => ['nullable', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'email.unique' => 'This email is already registered.',
        ];
    }
}

// In controller:
public function store(CreateUserRequest $request): JsonResponse
{
    $validated = $request->validated();
    $user = User::create($validated);
    return new UserResource($user);
}
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Users\Dto;

use Lattice\Validation\Attributes\Email;
use Lattice\Validation\Attributes\InArray;
use Lattice\Validation\Attributes\Nullable;
use Lattice\Validation\Attributes\Required;
use Lattice\Validation\Attributes\StringType;
use Lattice\Validation\Attributes\Unique;

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

        #[InArray(values: ['admin', 'user', 'editor'])]
        public string $role = 'user',

        #[Nullable]
        #[StringType(maxLength: 30)]
        public ?string $phone = null,

        #[Nullable]
        #[StringType(maxLength: 500)]
        public ?string $bio = null,
    ) {}
}

// In controller — DTO is auto-validated and injected:
#[Post('/')]
public function store(#[Body] CreateUserDto $dto): Response
{
    $user = $this->service->create($dto);
    return ResponseFactory::created(['data' => UserResource::make($user)->toArray()]);
}
```

**Key differences:**
- Validation rules become attributes on constructor parameters
- The DTO is immutable (`readonly class`)
- Auto-validated before the controller method runs
- `#[Body]` attribute tells the framework to deserialize the request body into the DTO
- No `authorize()` method — use `#[UseGuards]` on the controller instead
- Invalid input returns 422 with structured error response automatically

---

### 3.4 Eloquent Model (Minimal Changes)

**Laravel:**

```php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Model
{
    use SoftDeletes, HasFactory;

    protected $fillable = ['name', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime'];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }
}
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Lattice\Database\Model;

final class User extends Model
{
    use SoftDeletes;
    use HasFactory;

    protected $fillable = ['name', 'email', 'password', 'role'];
    protected $hidden = ['password', 'remember_token'];
    protected $casts = ['email_verified_at' => 'datetime'];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }
}
```

**What changed:**
- `Illuminate\Database\Eloquent\Model` becomes `Lattice\Database\Model` (which extends it)
- Add `declare(strict_types=1)` and explicit return types
- Add `final` keyword (LatticePHP convention: final by default)
- Everything else (relationships, scopes, casts, accessors, mutators) works identically

---

### 3.5 Controller: Thin Controller with Service Injection

**Laravel:**

```php
class OrderController extends Controller
{
    public function store(CreateOrderRequest $request): JsonResponse
    {
        $order = Order::create([
            'user_id' => $request->user()->id,
            'product_id' => $request->product_id,
            'quantity' => $request->quantity,
            'total' => $request->quantity * Product::find($request->product_id)->price,
        ]);

        event(new OrderCreated($order));

        return (new OrderResource($order))
            ->response()
            ->setStatusCode(201);
    }
}
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use Lattice\Auth\Principal;
use Lattice\Http\Response;
use Lattice\Http\ResponseFactory;
use Lattice\Routing\Attributes\Body;
use Lattice\Routing\Attributes\Controller;
use Lattice\Routing\Attributes\CurrentUser;
use Lattice\Routing\Attributes\Post;

#[Controller('/api/orders')]
#[UseGuards(guards: [JwtAuthenticationGuard::class])]
final class OrderController
{
    public function __construct(
        private readonly OrderService $service,
    ) {}

    #[Post('/')]
    public function store(#[Body] CreateOrderDto $dto, #[CurrentUser] Principal $user): Response
    {
        $order = $this->service->create($dto, $user);
        return ResponseFactory::created(['data' => OrderResource::make($order)->toArray()]);
    }
}
```

**Key differences:**
- Business logic lives in the service, not the controller
- `$request->user()` becomes `#[CurrentUser] Principal $user`
- Events are dispatched inside the service layer
- No `extends Controller` base class needed

---

### 3.6 Event and Listener

**Laravel:**

```php
// app/Events/OrderCreated.php
class OrderCreated
{
    public function __construct(public Order $order) {}
}

// app/Listeners/SendOrderConfirmation.php
class SendOrderConfirmation
{
    public function handle(OrderCreated $event): void
    {
        Mail::to($event->order->user)->send(new OrderConfirmationMail($event->order));
    }
}

// app/Providers/EventServiceProvider.php
protected $listen = [
    OrderCreated::class => [
        SendOrderConfirmation::class,
    ],
];
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

// app/Modules/Orders/Events/OrderCreated.php
namespace App\Modules\Orders\Events;

final readonly class OrderCreated
{
    public function __construct(
        public int $orderId,
        public int $userId,
        public float $total,
    ) {}
}

// app/Modules/Orders/Listeners/SendOrderConfirmation.php
namespace App\Modules\Orders\Listeners;

use App\Modules\Orders\Events\OrderCreated;
use Lattice\Events\Attributes\Listener;

#[Listener(event: OrderCreated::class)]
final class SendOrderConfirmation
{
    public function __construct(
        private readonly MailService $mailer,
        private readonly OrderRepository $orders,
    ) {}

    public function handle(OrderCreated $event): void
    {
        $order = $this->orders->findOrFail($event->orderId);
        $this->mailer->sendOrderConfirmation($order);
    }
}
```

**Key differences:**
- No `EventServiceProvider` registration. The `#[Listener]` attribute auto-registers.
- Events should use primitive/serializable data, not Eloquent models.
- The listener class is auto-discovered by the compiler.

---

### 3.7 Job / Queue

**Laravel:**

```php
// app/Jobs/ProcessPayment.php
class ProcessPayment implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private Order $order) {}

    public function handle(PaymentGateway $gateway): void
    {
        $gateway->charge($this->order->total, $this->order->user->stripe_id);
    }
}

// Dispatch:
ProcessPayment::dispatch($order);
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payments\Jobs;

use Lattice\Queue\Job;

final class ProcessPaymentJob implements Job
{
    public function __construct(
        private readonly int $orderId,
        private readonly float $amount,
        private readonly string $stripeCustomerId,
    ) {}

    public function handle(PaymentGateway $gateway): void
    {
        $gateway->charge($this->amount, $this->stripeCustomerId);
    }
}

// Dispatch (inject QueueDispatcher):
$this->queue->dispatch(new ProcessPaymentJob(
    orderId: $order->id,
    amount: $order->total,
    stripeCustomerId: $order->user->stripe_id,
));
```

**Key differences:**
- No `Dispatchable` trait, no static `dispatch()` method
- Pass primitive data to the constructor, not Eloquent models (`SerializesModels` is gone)
- Dispatch via injected `QueueDispatcher`, not static method
- Same queue backends (Redis, SQS, database, etc.)

---

### 3.8 API Resource

**Laravel:**

```php
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role,
            'created_at' => $this->created_at->toIso8601String(),
            'orders_count' => $this->when($this->orders_count !== null, $this->orders_count),
        ];
    }
}
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Users;

use Lattice\Http\Resource;

final class UserResource extends Resource
{
    public function toArray(): array
    {
        return [
            'id' => $this->model->id,
            'name' => $this->model->name,
            'email' => $this->model->email,
            'role' => $this->model->role,
            'created_at' => $this->model->created_at?->toIso8601String(),
        ];
    }
}
```

**Key differences:**
- `$this->id` becomes `$this->model->id` (explicit model reference)
- `toArray()` takes no parameters
- `Resource::make($model)` and `Resource::collection($models)` work the same way

---

### 3.9 Policy

**Laravel:**

```php
// app/Policies/OrderPolicy.php
class OrderPolicy
{
    public function view(User $user, Order $order): bool
    {
        return $user->id === $order->user_id;
    }

    public function delete(User $user, Order $order): bool
    {
        return $user->id === $order->user_id || $user->isAdmin();
    }
}

// Registered in AuthServiceProvider:
protected $policies = [
    Order::class => OrderPolicy::class,
];

// Used in controller:
$this->authorize('view', $order);
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

namespace App\Modules\Orders;

use App\Models\Order;
use Lattice\Authorization\Attributes\Policy;
use Lattice\Auth\Principal;

#[Policy(model: Order::class)]
final class OrderPolicy
{
    public function view(Principal $user, Order $order): bool
    {
        return (int) $user->getId() === $order->user_id;
    }

    public function delete(Principal $user, Order $order): bool
    {
        return (int) $user->getId() === $order->user_id || $user->hasRole('admin');
    }
}

// Used on controller method:
#[Get('/:id')]
#[Can('view', Order::class)]
public function show(#[Param] int $id): Response { ... }
```

**Key differences:**
- `#[Policy]` attribute replaces `AuthServiceProvider` registration
- `#[Can]` attribute on controller methods replaces `$this->authorize()`
- `User` becomes `Principal` (the authenticated identity abstraction)

---

### 3.10 Factory (Same)

**Laravel:**

```php
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => bcrypt('password'),
            'role' => 'user',
        ];
    }
}
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

namespace App\Database\Factories;

use App\Models\User;
use Lattice\Database\Factory;

final class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password', // Model mutator handles hashing
            'role' => 'user',
        ];
    }
}
```

**What changed:** Only the base class import. Everything else is identical.

---

### 3.11 Migration (Same)

**Laravel:**

```php
return new class extends Migration {
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('total', 10, 2);
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;

return new class {
    public function up(): void
    {
        Capsule::schema()->create('orders', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->decimal('total', 10, 2);
            $table->string('status')->default('pending');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Capsule::schema()->dropIfExists('orders');
    }
};
```

**What changed:** `Schema::` becomes `Capsule::schema()->`. The `Blueprint` API is identical.

---

### 3.12 Service Provider to Module

**Laravel:**

```php
// app/Providers/AppServiceProvider.php
class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PaymentGateway::class, StripeGateway::class);
        $this->app->singleton(MailService::class, SmtpMailService::class);
    }

    public function boot(): void
    {
        // Register observers, macros, etc.
    }
}
```

**LatticePHP:**

```php
<?php

declare(strict_types=1);

namespace App;

use App\Modules\Auth\AuthModule;
use App\Modules\Orders\OrdersModule;
use App\Modules\Payments\PaymentsModule;
use App\Modules\Users\UsersModule;
use Lattice\Module\Attribute\Module;

#[Module(
    imports: [
        AuthModule::class,
        UsersModule::class,
        OrdersModule::class,
        PaymentsModule::class,
    ],
)]
final class AppModule {}
```

Each domain gets its own module:

```php
<?php

declare(strict_types=1);

namespace App\Modules\Payments;

use Lattice\Module\Attribute\Module;

#[Module(
    providers: [
        ['provide' => PaymentGateway::class, 'useClass' => StripeGateway::class],
    ],
    controllers: [
        PaymentController::class,
    ],
    exports: [
        PaymentGateway::class,
    ],
)]
final class PaymentsModule {}
```

---

### 3.13 Config and `.env` (Same)

**Laravel `config/database.php`:**

```php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
        ],
    ],
];
```

**LatticePHP `config/database.php`:**

```php
<?php

declare(strict_types=1);

return [
    'default' => env('DB_CONNECTION', 'mysql'),
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'forge'),
            'username' => env('DB_USERNAME', 'forge'),
            'password' => env('DB_PASSWORD', ''),
        ],
    ],
];
```

**Identical.** Copy the file. Same `env()` function, same `config()` accessor.

---

## 4. What Works the Same (Copy Directly)

These require zero or minimal changes:

| Component | Change Required |
|---|---|
| Eloquent models | Change base class import to `Lattice\Database\Model` |
| Relationships (hasMany, belongsTo, etc.) | None |
| Scopes (local and global) | None |
| Accessors and mutators | None |
| Casts | None |
| Database migrations | Use `Capsule::schema()` instead of `Schema::` facade |
| Factories | Change base class to `Lattice\Database\Factory` |
| Seeders | None |
| `.env` files | None |
| `config/*.php` files | None |
| Illuminate Collections | None (`collect()`, `Str::`, `Arr::`, `Carbon`) |
| Query Builder | None |
| Validation rules (logic) | Same rules, different syntax (attributes vs. arrays) |
| Hashing (`password_hash`) | None |

---

## 5. What Is Different (Must Convert)

| Laravel Pattern | LatticePHP Pattern | Why |
|---|---|---|
| `routes/api.php` file | `#[Controller]` + `#[Get]`/`#[Post]` attributes | Routes belong to controllers, not a global file |
| Service Providers | `#[Module]` classes | Explicit dependency graph, not boot-order |
| Middleware | Guards + Interceptors | Separated by concern (auth vs. cross-cutting) |
| Form Requests | DTOs with validation attributes | Immutable, typed, attribute-validated |
| Facades (`Cache::`, `Log::`) | Constructor injection | Explicit dependencies, no magic |
| `app()` helper | Inject via constructor | No service locator pattern |
| `$this->authorize()` | `#[Can]` attribute | Declarative authorization |
| Route Model Binding | `#[Param]` + explicit lookup | No implicit magic |
| `EventServiceProvider::$listen` | `#[Listener]` attribute | Auto-discovered, no manual registration |
| `php artisan` | `php lattice` | Different CLI entry point |
| Blade templates | N/A | Backend-only framework |
| Notification system | Custom service / Activity | No built-in channel abstraction |
| Broadcasting | Event transport / WebSocket adapter | Different architecture |

---

## 6. What Is New (Not in Laravel)

### 6.1 Module System

Every feature lives in a self-contained module with explicit imports/exports:

```php
#[Module(
    imports: [DatabaseModule::class, CacheModule::class],
    providers: [OrderService::class, OrderRepository::class],
    controllers: [OrderController::class],
    exports: [OrderService::class], // Other modules can use this
)]
final class OrdersModule {}
```

Modules create clear dependency boundaries. If `OrdersModule` needs `PaymentGateway`, it must import `PaymentsModule`, which must export `PaymentGateway`.

### 6.2 Workspace / Multi-Tenancy

Built-in workspace isolation via the `BelongsToWorkspace` trait and `WorkspaceGuard`:

```php
// Model
final class Contact extends Model
{
    use BelongsToWorkspace; // Auto-scopes queries to current workspace
}

// Controller — guard enforces workspace context
#[Controller('/api/contacts')]
#[UseGuards(guards: [JwtAuthenticationGuard::class, WorkspaceGuard::class])]
final class ContactController { ... }
```

Data is automatically scoped — queries only return rows matching the current workspace.

### 6.3 Durable Workflow Engine

LatticePHP includes a native Temporal-like workflow engine. No external Temporal service required:

```php
#[Workflow('onboard-customer')]
final class OnboardCustomerWorkflow
{
    #[WorkflowMain]
    public function execute(int $customerId): void
    {
        // Each activity is durable — survives process crashes
        $this->activity(CreateAccount::class, $customerId);
        $this->activity(SendWelcomeEmail::class, $customerId);
        $this->activity(AssignSalesRep::class, $customerId);

        // Wait for external signal (e.g., customer completes profile)
        $this->waitForSignal('profile-completed', timeout: 86400);

        $this->activity(ActivateSubscription::class, $customerId);
    }
}
```

Features: deterministic replay, event sourcing, compensation/saga, signals, queries, timers.

### 6.4 CQRS Support

Separate read and write models:

```php
#[Command]
final class CreateOrderCommand
{
    public function __construct(
        public readonly int $userId,
        public readonly array $items,
    ) {}
}

#[Query]
final class GetOrdersByUserQuery
{
    public function __construct(
        public readonly int $userId,
    ) {}
}
```

### 6.5 Circuit Breaker

Built-in resilience for external service calls:

```php
#[CircuitBreaker(failureThreshold: 5, resetTimeout: 30)]
public function callExternalApi(): Response
{
    return $this->httpClient->get('https://api.external.com/data');
}
```

### 6.6 Transport-Aware Architecture

Controllers can serve HTTP, gRPC, and message-based transports:

```php
#[Controller('/orders')]
final class OrderController
{
    // This same handler works for HTTP requests AND gRPC calls
    #[Post('/')]
    public function create(#[Body] CreateOrderDto $dto): OrderResource { ... }
}
```

### 6.7 Attribute-Based Compilation

In production, all attribute metadata is compiled into a manifest (no reflection at runtime):

```bash
php lattice compile
```

This makes the framework faster than Laravel in production because there is zero reflection overhead.

---

## 7. Migration Checklist

Use this checklist to track your conversion:

- [ ] Create new LatticePHP project
- [ ] Copy `.env`, `config/`, migrations, factories, seeders
- [ ] Identify domain boundaries and plan module structure
- [ ] Create `AppModule` with all module imports
- [ ] Convert models (change base class, add `final`, add return types)
- [ ] Create DTOs for each Form Request
- [ ] Convert controllers (add route attributes, inject services, use DTOs)
- [ ] Convert middleware to guards and interceptors
- [ ] Convert event listeners to `#[Listener]` classes
- [ ] Convert jobs (use constructor with primitives, inject `QueueDispatcher`)
- [ ] Convert policies to `#[Policy]` classes
- [ ] Replace all facade usage with constructor injection
- [ ] Replace `app()` calls with constructor injection
- [ ] Replace `$this->authorize()` with `#[Can]` attributes
- [ ] Convert API Resources (change base class, use `$this->model->`)
- [ ] Add workspace support if multi-tenant
- [ ] Run `php lattice compile` for production
- [ ] Run full test suite

---

## 8. Common Gotchas

1. **No implicit model binding.** You must use `#[Param]` to get the ID and look up the model yourself in the service layer.

2. **No global helpers in services.** Replace `app()`, `config()`, `env()` with injected dependencies. `env()` should only be used in config files.

3. **Modules must export providers.** If Module A needs a service from Module B, Module B must list that service in `exports`.

4. **DTOs are readonly.** You cannot mutate a DTO after construction. If you need to transform data, create a new DTO or work with arrays in the service layer.

5. **No Blade, no views, no frontend.** If your Laravel app serves HTML, the frontend must be a separate SPA/SSR application that calls your LatticePHP API.

6. **Routes use `:param` syntax, not `{param}`.** Write `/:id` not `/{id}`.

7. **No `artisan`.** The CLI is `php lattice`. Most generator commands have equivalents.

8. **Workspace scoping is automatic.** If a model uses `BelongsToWorkspace`, all queries are automatically scoped. You do not need to manually add `where('workspace_id', ...)`.

9. **Guards are ordered.** In `#[UseGuards(guards: [A::class, B::class])]`, guard A runs before guard B. If A rejects, B never runs.

10. **Compile in production.** Always run `php lattice compile` before deploying. This eliminates runtime reflection and dramatically improves performance.
