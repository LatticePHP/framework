<?php

declare(strict_types=1);

namespace Lattice\Pat\Tests;

use Lattice\Auth\Principal;
use Lattice\Contracts\Context\PrincipalInterface;
use Lattice\Pat\CreateTokenResult;
use Lattice\Pat\PatManager;
use Lattice\Pat\Store\InMemoryPatStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PatManagerTest extends TestCase
{
    private PatManager $manager;
    private InMemoryPatStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryPatStore();
        $this->manager = new PatManager($this->store);
    }

    #[Test]
    public function it_creates_a_token_and_returns_plain_text(): void
    {
        $principal = new Principal(id: 'user-42', type: 'user', scopes: ['read', 'write']);

        $result = $this->manager->create($principal, 'CI Token', ['read']);

        $this->assertInstanceOf(CreateTokenResult::class, $result);
        $this->assertNotEmpty($result->tokenId);
        $this->assertNotEmpty($result->plainToken);
        $this->assertSame('CI Token', $result->name);
        $this->assertSame(['read'], $result->scopes);
        $this->assertNull($result->expiresAt);
    }

    #[Test]
    public function it_creates_token_with_expiration(): void
    {
        $principal = new Principal(id: 'user-42');
        $expiresAt = new \DateTimeImmutable('+30 days');

        $result = $this->manager->create($principal, 'Temp Token', [], $expiresAt);

        $this->assertEquals($expiresAt, $result->expiresAt);
    }

    #[Test]
    public function it_validates_a_valid_token(): void
    {
        $principal = new Principal(id: 'user-42', type: 'user', scopes: ['read']);
        $result = $this->manager->create($principal, 'My Token', ['read']);

        $resolved = $this->manager->validate($result->plainToken);

        $this->assertInstanceOf(PrincipalInterface::class, $resolved);
        $this->assertSame('user-42', $resolved->getId());
    }

    #[Test]
    public function it_returns_null_for_invalid_token(): void
    {
        $this->assertNull($this->manager->validate('nonexistent-token'));
    }

    #[Test]
    public function it_returns_null_for_expired_token(): void
    {
        $principal = new Principal(id: 'user-42');
        $expiresAt = new \DateTimeImmutable('-1 hour');

        $result = $this->manager->create($principal, 'Expired Token', [], $expiresAt);

        $this->assertNull($this->manager->validate($result->plainToken));
    }

    #[Test]
    public function it_revokes_a_token_by_id(): void
    {
        $principal = new Principal(id: 'user-42');
        $result = $this->manager->create($principal, 'Token', ['read']);

        $this->manager->revoke($result->tokenId);

        $this->assertNull($this->manager->validate($result->plainToken));
    }

    #[Test]
    public function it_revokes_all_tokens_for_a_principal(): void
    {
        $principal = new Principal(id: 'user-42');
        $result1 = $this->manager->create($principal, 'Token 1');
        $result2 = $this->manager->create($principal, 'Token 2');

        $this->manager->revokeAllForPrincipal('user-42');

        $this->assertNull($this->manager->validate($result1->plainToken));
        $this->assertNull($this->manager->validate($result2->plainToken));
    }

    #[Test]
    public function it_hashes_token_before_storage(): void
    {
        $principal = new Principal(id: 'user-42');
        $result = $this->manager->create($principal, 'Token');

        // The plain token should not be stored; only its hash should be in the store
        $hash = hash('sha256', $result->plainToken);
        $stored = $this->store->findByHash($hash);

        $this->assertNotNull($stored);
        $this->assertNotSame($result->plainToken, $stored->hash);
    }

    #[Test]
    public function each_token_has_unique_id_and_plain_text(): void
    {
        $principal = new Principal(id: 'user-42');
        $result1 = $this->manager->create($principal, 'Token 1');
        $result2 = $this->manager->create($principal, 'Token 2');

        $this->assertNotSame($result1->tokenId, $result2->tokenId);
        $this->assertNotSame($result1->plainToken, $result2->plainToken);
    }
}
