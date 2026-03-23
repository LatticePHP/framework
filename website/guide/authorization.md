---
outline: deep
---

# Authorization

LatticePHP provides attribute-based authorization with roles, scopes, policies, and a gate system. Authorization is checked after authentication -- it answers "is this user allowed to do this?" rather than "who is this user?"

## Attribute-Based Access Control

### Require Authentication

`#[Authorize]` requires any authenticated user:

```php
use Lattice\Auth\Attributes\Authorize;

#[Controller('/api/profile')]
#[Authorize]
final class ProfileController
{
    #[Get('/')]
    public function me(#[CurrentUser] Principal $user): Response
    {
        // Any authenticated user can access this
    }
}
```

### Require Roles

`#[Roles]` restricts access to users with specific roles:

```php
use Lattice\Auth\Attributes\Roles;

#[Controller('/api/admin')]
#[Authorize]
#[Roles(roles: ['admin', 'super-admin'])]
final class AdminController
{
    #[Get('/reports')]
    public function reports(): Response
    {
        // Only admin or super-admin
    }

    #[Delete('/users/:id')]
    #[Roles(roles: ['super-admin'])]
    public function deleteUser(#[Param] int $id): Response
    {
        // Only super-admin (method-level overrides class-level)
    }
}
```

### Require Scopes

`#[Scopes]` checks that the token has specific scopes (useful with OAuth2 and PATs):

```php
use Lattice\Auth\Attributes\Scopes;

#[Get('/reports')]
#[Scopes(scopes: ['reports:read'])]
public function reports(): Response { /* ... */ }

#[Post('/reports')]
#[Scopes(scopes: ['reports:write'])]
public function create(): Response { /* ... */ }
```

## Role-Based Access Control (RBAC)

### HasRoles Trait

The `User` model uses the `HasRoles` trait for role management:

```php
use Lattice\Auth\Traits\HasRoles;

final class User extends Model
{
    use HasRoles;
}

// Assign and check roles
$user->assignRole('editor');
$user->removeRole('editor');
$user->hasRole('admin');           // true/false
$user->getRoleNames();             // ['admin', 'editor']
```

### HasPermissions Trait

Check permissions directly and through roles:

```php
use Lattice\Auth\Traits\HasPermissions;

final class User extends Model
{
    use HasRoles;
    use HasPermissions;
}

$user->givePermission('contacts.create');
$user->revokePermission('contacts.create');
$user->hasPermission('contacts.create');   // Checks direct + role permissions
$user->can('contacts.create');             // Same, with super-admin bypass
```

::: tip
Users with the `super-admin` role bypass all `can()` checks automatically.
:::

## Policies

Policies define resource-level authorization rules. Use `#[Policy]` to register a policy and `#[Can]` to check it on controller methods:

```php
use Lattice\Auth\Attributes\Policy;
use Lattice\Auth\Attributes\Can;

#[Policy(model: Contact::class)]
final class ContactPolicy
{
    public function view(Principal $user, Contact $contact): bool
    {
        return (int) $user->getId() === $contact->owner_id;
    }

    public function update(Principal $user, Contact $contact): bool
    {
        return (int) $user->getId() === $contact->owner_id
            || $user->hasRole('admin');
    }

    public function delete(Principal $user, Contact $contact): bool
    {
        return $user->hasRole('admin');
    }
}
```

Apply policies to controller methods:

```php
#[Get('/:id')]
#[Can('view', Contact::class)]
public function show(#[Param] int $id): Response { /* ... */ }

#[Put('/:id')]
#[Can('update', Contact::class)]
public function update(#[Param] int $id, #[Body] UpdateContactDto $dto): Response { /* ... */ }

#[Delete('/:id')]
#[Can('delete', Contact::class)]
public function destroy(#[Param] int $id): Response { /* ... */ }
```

## Gate

The `Gate` class provides a programmatic way to define and check authorization abilities:

```php
use Lattice\Authorization\Gate;

$gate = new Gate();

// Define abilities
$gate->define('edit-contact', function (PrincipalInterface $user, Contact $contact) {
    return $user->getId() === $contact->owner_id;
});

// Global "before" hook (super-admin bypass)
$gate->before(function (PrincipalInterface $user) {
    if ($user->hasRole('super-admin')) return true;
    return null; // continue to normal check
});

// Check abilities
$gate->allows($principal, 'edit-contact', $contact);     // true/false
$gate->denies($principal, 'edit-contact', $contact);      // true/false
$gate->authorize($principal, 'edit-contact', $contact);   // throws ForbiddenException
```

### Scoped Gate

Check abilities for a specific user:

```php
$userGate = $gate->forUser($principal);
$userGate->allows(null, 'edit-contact', $contact);
```

## Tenant-Aware Authorization

The `TenantAwareChecker` ensures authorization checks respect tenant boundaries:

```php
use Lattice\Authorization\TenantAwareChecker;

// Ensures the user and the resource belong to the same tenant
$checker = new TenantAwareChecker($gate, $tenantContext);
$checker->authorize($principal, 'update', $contact);
```

## Next Steps

- [Authentication](auth.md) -- JWT, guards, and the principal object
- [Security](security.md) -- security best practices and checklist
- [API Keys & PATs](api-keys.md) -- alternative auth with scopes
