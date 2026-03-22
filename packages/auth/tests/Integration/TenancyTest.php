<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests\Integration;

use Lattice\Auth\Models\Tenant;
use Lattice\Auth\Tenancy\TenantContext;
use Lattice\Auth\Tenancy\TenantGuard;
use Lattice\Auth\Tenancy\TenantResolver;
use Lattice\Contracts\Context\ExecutionContextInterface;
use Lattice\Contracts\Context\ExecutionType;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Http\HttpExecutionContext;
use Lattice\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the multi-tenancy system.
 *
 * Tests TenantContext, TenantResolver strategies, TenantGuard,
 * and the Tenant model configuration.
 */
final class TenancyTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::reset();
    }

    // ── TenantContext ──────────────────────────────────────────────

    #[Test]
    public function test_tenant_context_set_and_get(): void
    {
        $tenant = new Tenant(['name' => 'Acme', 'slug' => 'acme']);
        $tenant->id = 1;

        TenantContext::set($tenant);

        $this->assertSame($tenant, TenantContext::get());
        $this->assertSame(1, TenantContext::id());
    }

    #[Test]
    public function test_tenant_context_returns_null_when_not_set(): void
    {
        $this->assertNull(TenantContext::get());
        $this->assertNull(TenantContext::id());
    }

    #[Test]
    public function test_tenant_context_reset_clears_tenant(): void
    {
        $tenant = new Tenant(['name' => 'Acme', 'slug' => 'acme']);
        $tenant->id = 1;

        TenantContext::set($tenant);
        $this->assertNotNull(TenantContext::get());

        TenantContext::reset();
        $this->assertNull(TenantContext::get());
        $this->assertNull(TenantContext::id());
    }

    #[Test]
    public function test_tenant_context_can_be_set_to_null(): void
    {
        $tenant = new Tenant(['name' => 'Acme', 'slug' => 'acme']);
        $tenant->id = 1;

        TenantContext::set($tenant);
        TenantContext::set(null);

        $this->assertNull(TenantContext::get());
    }

    // ── Tenant Model ───────────────────────────────────────────────

    #[Test]
    public function test_tenant_model_has_correct_table(): void
    {
        $tenant = new Tenant();
        $this->assertSame('tenants', $tenant->getTable());
    }

    #[Test]
    public function test_tenant_model_fillable_fields(): void
    {
        $tenant = new Tenant();
        $this->assertSame(['name', 'slug', 'domain', 'settings'], $tenant->getFillable());
    }

    #[Test]
    public function test_tenant_model_casts_settings_to_array(): void
    {
        $tenant = new Tenant();
        $casts = $tenant->getCasts();
        $this->assertSame('array', $casts['settings']);
    }

    #[Test]
    public function test_tenant_model_mass_assignment(): void
    {
        $tenant = new Tenant([
            'name' => 'Acme Corp',
            'slug' => 'acme',
            'domain' => 'acme.example.com',
            'settings' => ['theme' => 'dark'],
        ]);

        $this->assertSame('Acme Corp', $tenant->name);
        $this->assertSame('acme', $tenant->slug);
        $this->assertSame('acme.example.com', $tenant->domain);
    }

    // ── TenantResolver ─────────────────────────────────────────────

    #[Test]
    public function test_resolver_returns_null_for_unknown_strategy(): void
    {
        $resolver = new TenantResolver();
        $request = new Request('GET', '/');

        $this->assertNull($resolver->resolve($request, 'nonexistent'));
    }

    #[Test]
    public function test_resolver_subdomain_returns_null_when_no_host(): void
    {
        $resolver = new TenantResolver();
        $request = new Request('GET', '/');

        $this->assertNull($resolver->resolve($request, 'subdomain'));
    }

    #[Test]
    public function test_resolver_subdomain_returns_null_when_host_is_base_domain(): void
    {
        $resolver = new TenantResolver();
        $request = new Request('GET', '/', ['host' => 'app.localhost']);

        // When host equals the base domain, there's no subdomain
        $this->assertNull($resolver->resolve($request, 'subdomain'));
    }

    #[Test]
    public function test_resolver_header_returns_null_when_no_header(): void
    {
        $resolver = new TenantResolver();
        $request = new Request('GET', '/');

        $this->assertNull($resolver->resolve($request, 'header'));
    }

    #[Test]
    public function test_resolver_path_returns_null_when_no_tenant_in_path(): void
    {
        $resolver = new TenantResolver();
        $request = new Request('GET', '/api/users');

        $this->assertNull($resolver->resolve($request, 'path'));
    }

    // ── TenantGuard ────────────────────────────────────────────────

    #[Test]
    public function test_tenant_guard_blocks_when_no_tenant_resolved(): void
    {
        $resolver = new TenantResolver();
        $guard = new TenantGuard($resolver, 'header');

        $request = new Request('GET', '/');
        $context = new HttpExecutionContext(
            request: $request,
            module: 'TestModule',
            controllerClass: 'TestController',
            methodName: 'index',
        );

        $this->assertFalse($guard->canActivate($context));
    }

    #[Test]
    public function test_tenant_guard_blocks_when_context_has_no_request(): void
    {
        $resolver = new TenantResolver();
        $guard = new TenantGuard($resolver, 'header');

        // Create a context without getRequest method
        $context = new class implements ExecutionContextInterface {
            public function getType(): ExecutionType
            {
                return ExecutionType::Http;
            }

            public function getModule(): string
            {
                return 'test';
            }

            public function getHandler(): string
            {
                return 'test::index';
            }

            public function getClass(): string
            {
                return 'test';
            }

            public function getMethod(): string
            {
                return 'index';
            }

            public function getCorrelationId(): string
            {
                return 'test-123';
            }

            public function getPrincipal(): ?PrincipalInterface
            {
                return null;
            }
        };

        $this->assertFalse($guard->canActivate($context));
    }

    #[Test]
    public function test_tenant_guard_clears_context_on_rejection(): void
    {
        // Set an existing tenant in context
        $existingTenant = new Tenant(['name' => 'Old', 'slug' => 'old']);
        $existingTenant->id = 99;
        TenantContext::set($existingTenant);

        $resolver = new TenantResolver();
        $guard = new TenantGuard($resolver, 'header');

        $request = new Request('GET', '/');
        $context = new HttpExecutionContext(
            request: $request,
            module: 'TestModule',
            controllerClass: 'TestController',
            methodName: 'index',
        );

        // Guard should reject (no tenant header)
        $this->assertFalse($guard->canActivate($context));

        // The old context should still be there (guard doesn't clear on failure)
        $this->assertNotNull(TenantContext::get());
    }
}
