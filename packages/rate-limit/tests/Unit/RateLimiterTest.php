<?php

declare(strict_types=1);

namespace Lattice\RateLimit\Tests\Unit;

use Lattice\RateLimit\RateLimiter;
use Lattice\RateLimit\RateLimitResult;
use Lattice\RateLimit\Store\InMemoryRateLimitStore;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(RateLimiter::class)]
#[CoversClass(RateLimitResult::class)]
final class RateLimiterTest extends TestCase
{
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->limiter = new RateLimiter(new InMemoryRateLimitStore());
    }

    #[Test]
    public function it_allows_attempts_under_limit(): void
    {
        $result = $this->limiter->attempt('user:1', maxAttempts: 5, decaySeconds: 60);

        $this->assertTrue($result->allowed);
        $this->assertSame(4, $result->remaining);
        $this->assertNull($result->retryAfter);
        $this->assertSame(5, $result->limit);
    }

    #[Test]
    public function it_blocks_when_limit_exceeded(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt('user:1', maxAttempts: 3, decaySeconds: 60);
        }

        $result = $this->limiter->attempt('user:1', maxAttempts: 3, decaySeconds: 60);

        $this->assertFalse($result->allowed);
        $this->assertSame(0, $result->remaining);
        $this->assertNotNull($result->retryAfter);
        $this->assertSame(3, $result->limit);
    }

    #[Test]
    public function it_returns_remaining_count(): void
    {
        $this->limiter->attempt('user:1', maxAttempts: 5, decaySeconds: 60);
        $this->limiter->attempt('user:1', maxAttempts: 5, decaySeconds: 60);

        $this->assertSame(3, $this->limiter->remaining('user:1', maxAttempts: 5));
    }

    #[Test]
    public function it_clears_rate_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->attempt('user:1', maxAttempts: 5, decaySeconds: 60);
        }

        $this->limiter->clear('user:1');

        $result = $this->limiter->attempt('user:1', maxAttempts: 5, decaySeconds: 60);
        $this->assertTrue($result->allowed);
        $this->assertSame(4, $result->remaining);
    }

    #[Test]
    public function it_tracks_separate_keys_independently(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->limiter->attempt('user:1', maxAttempts: 3, decaySeconds: 60);
        }

        $result = $this->limiter->attempt('user:2', maxAttempts: 3, decaySeconds: 60);
        $this->assertTrue($result->allowed);
    }

    #[Test]
    public function remaining_returns_max_for_unknown_key(): void
    {
        $this->assertSame(10, $this->limiter->remaining('unknown', maxAttempts: 10));
    }
}
