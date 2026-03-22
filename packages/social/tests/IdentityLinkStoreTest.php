<?php

declare(strict_types=1);

namespace Lattice\Social\Tests;

use Lattice\Social\IdentityLink;
use Lattice\Social\Store\InMemoryIdentityLinkStore;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class IdentityLinkStoreTest extends TestCase
{
    private InMemoryIdentityLinkStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryIdentityLinkStore();
    }

    #[Test]
    public function it_links_a_provider_identity_to_local_user(): void
    {
        $link = $this->store->link('user-1', 'github', 'gh-123');

        $this->assertInstanceOf(IdentityLink::class, $link);
        $this->assertSame('user-1', $link->localUserId);
        $this->assertSame('github', $link->providerName);
        $this->assertSame('gh-123', $link->providerUserId);
        $this->assertInstanceOf(\DateTimeImmutable::class, $link->linkedAt);
    }

    #[Test]
    public function it_finds_link_by_provider(): void
    {
        $this->store->link('user-1', 'github', 'gh-123');

        $found = $this->store->findByProvider('github', 'gh-123');

        $this->assertNotNull($found);
        $this->assertSame('user-1', $found->localUserId);
    }

    #[Test]
    public function it_returns_null_for_unknown_provider_identity(): void
    {
        $this->assertNull($this->store->findByProvider('github', 'unknown'));
    }

    #[Test]
    public function it_finds_all_links_for_local_user(): void
    {
        $this->store->link('user-1', 'github', 'gh-123');
        $this->store->link('user-1', 'google', 'g-456');
        $this->store->link('user-2', 'github', 'gh-789');

        $links = $this->store->findByLocalUser('user-1');

        $this->assertCount(2, $links);
        $providers = array_map(fn(IdentityLink $l) => $l->providerName, $links);
        $this->assertContains('github', $providers);
        $this->assertContains('google', $providers);
    }

    #[Test]
    public function it_unlinks_a_provider_identity(): void
    {
        $this->store->link('user-1', 'github', 'gh-123');

        $this->store->unlink('github', 'gh-123');

        $this->assertNull($this->store->findByProvider('github', 'gh-123'));
        $this->assertCount(0, $this->store->findByLocalUser('user-1'));
    }

    #[Test]
    public function it_returns_empty_array_for_user_with_no_links(): void
    {
        $this->assertSame([], $this->store->findByLocalUser('user-99'));
    }
}
