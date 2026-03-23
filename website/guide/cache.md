---
outline: deep
---

# Cache

LatticePHP provides a unified cache layer with multiple drivers. Use it to store expensive query results, API responses, or computed values.

## Basic Usage

Inject `CacheInterface` or use the `Cache` facade:

```php
use Lattice\Cache\Cache;

// Store a value for 60 minutes
Cache::put('user:42', $userData, ttl: 3600);

// Retrieve (returns null if expired or missing)
$data = Cache::get('user:42');

// Retrieve with a default
$data = Cache::get('user:42', default: []);

// Check existence
if (Cache::has('user:42')) { /* ... */ }

// Remove
Cache::forget('user:42');

// Get or compute (stores the result if missing)
$data = Cache::remember('expensive:query', ttl: 3600, callback: function () {
    return DB::table('analytics')->complexQuery()->get();
});
```

## The `#[Cacheable]` Attribute

Automatically cache method return values with the `#[Cacheable]` attribute:

```php
use Lattice\Cache\Attributes\Cacheable;

final class DashboardService
{
    #[Cacheable(key: 'dashboard:stats', ttl: 300)]
    public function getStats(): array
    {
        // This runs only if the cache is empty or expired
        return [
            'contacts' => Contact::count(),
            'deals' => Deal::where('stage', '!=', 'closed_lost')->sum('value'),
            'activities' => Activity::whereNull('completed_at')->count(),
        ];
    }
}
```

The `CacheInterceptor` handles caching transparently. The method executes on cache miss and the result is stored automatically.

::: tip
Use `#[Cacheable]` for expensive computations like dashboard stats, report generation, or complex aggregations. The TTL controls how stale the data can be.
:::

## Cache Drivers

Configure the cache driver in `config/cache.php`:

| Driver | Use Case | Configuration |
|---|---|---|
| `array` | Testing -- in-memory, cleared between requests | `CACHE_STORE=array` |
| `file` | Development -- file-based, no external dependency | `CACHE_STORE=file` |
| `redis` | Production -- fast, shared across workers | `CACHE_STORE=redis` |

```php
// config/cache.php
return [
    'default' => env('CACHE_STORE', 'file'),
    'stores' => [
        'array' => ['driver' => 'array'],
        'file'  => ['driver' => 'file', 'path' => storage_path('cache')],
        'redis' => ['driver' => 'redis', 'connection' => 'cache'],
    ],
    'prefix' => env('CACHE_PREFIX', 'lattice_cache_'),
];
```

## Cache Operations

```php
// Store permanently
Cache::forever('settings:global', $settings);

// Increment / decrement (atomic with Redis)
Cache::increment('api:calls:today');
Cache::decrement('inventory:item:42');

// Flush everything (use with caution)
Cache::flush();
```

## Testing

Use the `array` driver in tests. It resets between requests automatically:

```php
// phpunit.xml
<env name="CACHE_STORE" value="array" />
```

## Next Steps

- [Configuration](configuration.md) -- Redis connection setup
- [Observability](observability.md) -- cache hit/miss monitoring
- [Deployment](deployment.md) -- production cache configuration
