---
outline: deep
---

# Feature Flags

LatticePHP provides a feature flag system for toggling functionality at runtime without deploying new code.

## The `#[RequiresFeature]` Attribute

Gate controller methods behind feature flags:

```php
use Lattice\Core\FeatureFlag\Attributes\RequiresFeature;

#[Controller('/api/contacts')]
final class ContactController
{
    #[Get('/export')]
    #[RequiresFeature('contact-export')]
    public function export(): Response
    {
        // Only accessible when the 'contact-export' feature is enabled
        return ResponseFactory::json(['data' => Contact::all()]);
    }
}
```

If the feature is disabled, the request returns a 403 Forbidden response.

## Defining Features

Register features in your application:

```php
use Lattice\Core\FeatureFlag\Feature;

// Simple boolean flag
Feature::define('contact-export', enabled: true);

// Scoped flag (enabled per-user, per-workspace, etc.)
Feature::define('new-dashboard', enabled: function (Principal $user) {
    return $user->hasRole('beta-tester');
});
```

## The FeatureGuard

`FeatureGuard` implements `GuardInterface` and checks feature flags in the pipeline:

```php
use Lattice\Core\FeatureFlag\FeatureGuard;
use Lattice\Pipeline\Attributes\UseGuards;

#[Get('/beta')]
#[UseGuards(guards: [FeatureGuard::class])]
#[RequiresFeature('beta-features')]
public function beta(): Response
{
    return ResponseFactory::json(['message' => 'Welcome to the beta!']);
}
```

## Scoped Features

Enable features for specific scopes:

```php
use Lattice\Core\FeatureFlag\ScopedFeature;

// Enable for specific workspace IDs
$feature = new ScopedFeature(
    name: 'advanced-analytics',
    scope: 'workspace',
    enabledFor: [1, 5, 12],
);

// Enable for a percentage of users (gradual rollout)
$feature = new ScopedFeature(
    name: 'new-ui',
    scope: 'user',
    percentage: 25,  // 25% of users
);
```

## Checking Flags Programmatically

```php
use Lattice\Core\FeatureFlag\Feature;

if (Feature::isEnabled('contact-export')) {
    // Feature is on
}

if (Feature::isEnabled('new-dashboard', $user)) {
    // Feature is on for this user
}
```

## Next Steps

- [Authorization](authorization.md) -- roles and scopes
- [Pipeline](pipeline.md) -- guards and the request pipeline
- [Configuration](configuration.md) -- environment-based feature configuration
