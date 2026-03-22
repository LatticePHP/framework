<?php

declare(strict_types=1);

namespace Lattice\RateLimit\Tests\Unit;

use Lattice\RateLimit\Store\InMemoryRateLimitStore;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(InMemoryRateLimitStore::class)]
final class InMemoryRateLimitStoreTest extends TestCase
{
    private InMemoryRateLimitStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryRateLimitStore();
    }

    #[Test]
    public function it_returns_zero_for_unknown_key(): void
    {
        $this->assertSame(0, $this->store->get('unknown'));
    }

    #[Test]
    public function it_increments_counter(): void
    {
        $count = $this->store->increment('user:1', 60);
        $this->assertSame(1, $count);

        $count = $this->store->increment('user:1', 60);
        $this->assertSame(2, $count);
    }

    #[Test]
    public function it_gets_current_count(): void
    {
        $this->store->increment('user:1', 60);
        $this->store->increment('user:1', 60);

        $this->assertSame(2, $this->store->get('user:1'));
    }

    #[Test]
    public function it_resets_counter(): void
    {
        $this->store->increment('user:1', 60);
        $this->store->increment('user:1', 60);
        $this->store->reset('user:1');

        $this->assertSame(0, $this->store->get('user:1'));
    }

    #[Test]
    public function it_isolates_keys(): void
    {
        $this->store->increment('user:1', 60);
        $this->store->increment('user:2', 60);
        $this->store->increment('user:2', 60);

        $this->assertSame(1, $this->store->get('user:1'));
        $this->assertSame(2, $this->store->get('user:2'));
    }

    #[Test]
    public function it_expires_entries_after_decay(): void
    {
        // We use a 1-second decay and verify it expires
        $this->store->increment('user:1', 1);
        $this->assertSame(1, $this->store->get('user:1'));

        // Simulate waiting by using the store's expiry mechanism
        // In-memory store tracks expiry time; after decay, counter resets
        sleep(2);

        // After decay, increment should restart from 1
        $count = $this->store->increment('user:1', 1);
        $this->assertSame(1, $count);
    }
}
