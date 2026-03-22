<?php

declare(strict_types=1);

namespace Lattice\Pat\Tests;

use Lattice\Pat\Store\InMemoryPatStore;
use Lattice\Pat\StoredToken;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InMemoryPatStoreTest extends TestCase
{
    private InMemoryPatStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryPatStore();
    }

    #[Test]
    public function it_stores_and_retrieves_token_by_hash(): void
    {
        $hash = hash('sha256', 'plain-token');
        $expiresAt = new \DateTimeImmutable('+1 hour');

        $this->store->store('tok-1', $hash, 'user-1', 'My Token', ['read'], $expiresAt);

        $stored = $this->store->findByHash($hash);

        $this->assertInstanceOf(StoredToken::class, $stored);
        $this->assertSame('tok-1', $stored->id);
        $this->assertSame($hash, $stored->hash);
        $this->assertSame('user-1', $stored->principalId);
        $this->assertSame('My Token', $stored->name);
        $this->assertSame(['read'], $stored->scopes);
        $this->assertEquals($expiresAt, $stored->expiresAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $stored->createdAt);
    }

    #[Test]
    public function it_returns_null_for_unknown_hash(): void
    {
        $this->assertNull($this->store->findByHash('nonexistent'));
    }

    #[Test]
    public function it_deletes_token_by_id(): void
    {
        $hash = hash('sha256', 'plain-token');
        $this->store->store('tok-1', $hash, 'user-1', 'My Token', [], null);

        $this->store->delete('tok-1');

        $this->assertNull($this->store->findByHash($hash));
    }

    #[Test]
    public function it_deletes_all_tokens_for_principal(): void
    {
        $hash1 = hash('sha256', 'token-1');
        $hash2 = hash('sha256', 'token-2');
        $hash3 = hash('sha256', 'token-3');

        $this->store->store('tok-1', $hash1, 'user-1', 'Token 1', [], null);
        $this->store->store('tok-2', $hash2, 'user-1', 'Token 2', [], null);
        $this->store->store('tok-3', $hash3, 'user-2', 'Token 3', [], null);

        $this->store->deleteAllForPrincipal('user-1');

        $this->assertNull($this->store->findByHash($hash1));
        $this->assertNull($this->store->findByHash($hash2));
        $this->assertNotNull($this->store->findByHash($hash3));
    }

    #[Test]
    public function it_stores_token_with_null_expiration(): void
    {
        $hash = hash('sha256', 'token');
        $this->store->store('tok-1', $hash, 'user-1', 'Forever Token', ['admin'], null);

        $stored = $this->store->findByHash($hash);

        $this->assertNotNull($stored);
        $this->assertNull($stored->expiresAt);
    }
}
