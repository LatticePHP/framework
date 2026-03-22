<?php

declare(strict_types=1);

namespace Lattice\Jwt\Tests;

use Lattice\Jwt\RefreshTokenRecord;
use Lattice\Jwt\RefreshTokenStoreInterface;
use Lattice\Jwt\Store\InMemoryRefreshTokenStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryRefreshTokenStoreTest extends TestCase
{
    private InMemoryRefreshTokenStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryRefreshTokenStore();
    }

    #[Test]
    public function it_implements_refresh_token_store_interface(): void
    {
        $this->assertInstanceOf(RefreshTokenStoreInterface::class, $this->store);
    }

    #[Test]
    public function it_stores_and_finds_token(): void
    {
        $expiresAt = new \DateTimeImmutable('+1 day');

        $this->store->store('hash-abc', 'user-123', $expiresAt);

        $record = $this->store->find('hash-abc');

        $this->assertInstanceOf(RefreshTokenRecord::class, $record);
        $this->assertSame('hash-abc', $record->tokenHash);
        $this->assertSame('user-123', $record->principalId);
        $this->assertSame($expiresAt, $record->expiresAt);
        $this->assertNull($record->revokedAt);
    }

    #[Test]
    public function it_returns_null_for_unknown_token(): void
    {
        $record = $this->store->find('nonexistent');

        $this->assertNull($record);
    }

    #[Test]
    public function it_revokes_token(): void
    {
        $this->store->store('hash-abc', 'user-123', new \DateTimeImmutable('+1 day'));

        $this->store->revoke('hash-abc');

        $record = $this->store->find('hash-abc');

        $this->assertNotNull($record);
        $this->assertNotNull($record->revokedAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $record->revokedAt);
    }

    #[Test]
    public function it_revokes_all_for_principal(): void
    {
        $this->store->store('hash-1', 'user-123', new \DateTimeImmutable('+1 day'));
        $this->store->store('hash-2', 'user-123', new \DateTimeImmutable('+1 day'));
        $this->store->store('hash-3', 'user-456', new \DateTimeImmutable('+1 day'));

        $this->store->revokeAllForPrincipal('user-123');

        $this->assertNotNull($this->store->find('hash-1')->revokedAt);
        $this->assertNotNull($this->store->find('hash-2')->revokedAt);
        $this->assertNull($this->store->find('hash-3')->revokedAt);
    }

    #[Test]
    public function revoking_nonexistent_token_is_noop(): void
    {
        // Should not throw
        $this->store->revoke('nonexistent');

        $this->assertNull($this->store->find('nonexistent'));
    }

    #[Test]
    public function revoking_all_for_nonexistent_principal_is_noop(): void
    {
        // Should not throw
        $this->store->revokeAllForPrincipal('nonexistent');

        $this->assertTrue(true); // No exception = pass
    }
}
