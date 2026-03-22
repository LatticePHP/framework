<?php

declare(strict_types=1);

namespace Lattice\OAuth\Tests;

use Lattice\OAuth\ScopeRegistry;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ScopeRegistryTest extends TestCase
{
    private ScopeRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ScopeRegistry();
        $this->registry->register('read', 'Read access');
        $this->registry->register('write', 'Write access');
        $this->registry->register('admin', 'Administrative access');
    }

    #[Test]
    public function it_registers_and_checks_scopes(): void
    {
        $this->assertTrue($this->registry->has('read'));
        $this->assertTrue($this->registry->has('write'));
        $this->assertTrue($this->registry->has('admin'));
        $this->assertFalse($this->registry->has('delete'));
    }

    #[Test]
    public function it_returns_scope_description(): void
    {
        $this->assertSame('Read access', $this->registry->getDescription('read'));
        $this->assertNull($this->registry->getDescription('nonexistent'));
    }

    #[Test]
    public function it_returns_all_registered_scopes(): void
    {
        $all = $this->registry->all();

        $this->assertSame([
            'read' => 'Read access',
            'write' => 'Write access',
            'admin' => 'Administrative access',
        ], $all);
    }

    #[Test]
    public function it_validates_requested_scopes_against_allowed(): void
    {
        $result = $this->registry->validate(['read', 'write'], ['read', 'write', 'admin']);

        $this->assertSame(['read', 'write'], $result);
    }

    #[Test]
    public function it_throws_for_unknown_scope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown scope: delete');

        $this->registry->validate(['delete'], ['read', 'write']);
    }

    #[Test]
    public function it_throws_for_scope_not_allowed_for_client(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Scope not allowed for this client: admin');

        $this->registry->validate(['admin'], ['read', 'write']);
    }

    #[Test]
    public function it_returns_client_scopes_when_none_requested(): void
    {
        $result = $this->registry->validate([], ['read', 'write']);

        $this->assertSame(['read', 'write'], $result);
    }

    #[Test]
    public function it_supports_default_scopes(): void
    {
        $this->registry->setDefaultScopes(['read']);

        $result = $this->registry->validate([], ['read', 'write', 'admin']);

        $this->assertSame(['read'], $result);
    }

    #[Test]
    public function it_throws_when_setting_unknown_default_scope(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot set unknown scope as default');

        $this->registry->setDefaultScopes(['nonexistent']);
    }

    #[Test]
    public function it_returns_default_scopes(): void
    {
        $this->registry->setDefaultScopes(['read', 'write']);

        $this->assertSame(['read', 'write'], $this->registry->getDefaultScopes());
    }

    #[Test]
    public function it_intersects_defaults_with_allowed(): void
    {
        $this->registry->setDefaultScopes(['read', 'admin']);

        // Client only allows 'read' and 'write', so 'admin' default is excluded
        $result = $this->registry->validate([], ['read', 'write']);

        $this->assertSame(['read'], $result);
    }
}
