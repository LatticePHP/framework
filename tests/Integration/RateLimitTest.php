<?php

declare(strict_types=1);

namespace Tests\Integration;

use Lattice\RateLimit\RateLimiter;
use Lattice\RateLimit\Store\InMemoryRateLimitStore;

final class RateLimitTest extends TestCase
{
    private InMemoryRateLimitStore $store;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->store = new InMemoryRateLimitStore();
        $this->limiter = new RateLimiter($this->store);
    }

    public function test_allows_requests_under_limit(): void
    {
        $result = $this->limiter->attempt('client:192.168.1.1', 5, 60);

        $this->assertTrue($result->allowed);
        $this->assertSame(4, $result->remaining);
        $this->assertNull($result->retryAfter);
        $this->assertSame(5, $result->limit);
    }

    public function test_decrements_remaining_on_each_attempt(): void
    {
        $key = 'client:10.0.0.1';
        $maxAttempts = 3;

        $first = $this->limiter->attempt($key, $maxAttempts, 60);
        $this->assertSame(2, $first->remaining);

        $second = $this->limiter->attempt($key, $maxAttempts, 60);
        $this->assertSame(1, $second->remaining);

        $third = $this->limiter->attempt($key, $maxAttempts, 60);
        $this->assertSame(0, $third->remaining);
    }

    public function test_blocks_when_limit_is_reached(): void
    {
        $key = 'user:42';
        $maxAttempts = 2;

        $this->limiter->attempt($key, $maxAttempts, 60);
        $this->limiter->attempt($key, $maxAttempts, 60);

        $blocked = $this->limiter->attempt($key, $maxAttempts, 60);

        $this->assertFalse($blocked->allowed);
        $this->assertSame(0, $blocked->remaining);
        $this->assertSame(60, $blocked->retryAfter);
    }

    public function test_resets_counter_after_clear(): void
    {
        $key = 'endpoint:/api/login';
        $maxAttempts = 2;

        $this->limiter->attempt($key, $maxAttempts, 60);
        $this->limiter->attempt($key, $maxAttempts, 60);

        $blocked = $this->limiter->attempt($key, $maxAttempts, 60);
        $this->assertFalse($blocked->allowed);

        $this->limiter->clear($key);

        $afterClear = $this->limiter->attempt($key, $maxAttempts, 60);
        $this->assertTrue($afterClear->allowed);
        $this->assertSame(1, $afterClear->remaining);
    }

    public function test_remaining_returns_correct_count(): void
    {
        $key = 'api:v1';
        $maxAttempts = 5;

        $this->assertSame(5, $this->limiter->remaining($key, $maxAttempts));

        $this->limiter->attempt($key, $maxAttempts, 60);
        $this->assertSame(4, $this->limiter->remaining($key, $maxAttempts));

        $this->limiter->attempt($key, $maxAttempts, 60);
        $this->limiter->attempt($key, $maxAttempts, 60);
        $this->assertSame(2, $this->limiter->remaining($key, $maxAttempts));
    }

    public function test_independent_keys_do_not_interfere(): void
    {
        $this->limiter->attempt('user:1', 2, 60);
        $this->limiter->attempt('user:1', 2, 60);

        $blocked = $this->limiter->attempt('user:1', 2, 60);
        $this->assertFalse($blocked->allowed);

        $other = $this->limiter->attempt('user:2', 2, 60);
        $this->assertTrue($other->allowed);
        $this->assertSame(1, $other->remaining);
    }

    public function test_single_attempt_limit(): void
    {
        $key = 'strict';

        $first = $this->limiter->attempt($key, 1, 30);
        $this->assertTrue($first->allowed);
        $this->assertSame(0, $first->remaining);

        $second = $this->limiter->attempt($key, 1, 30);
        $this->assertFalse($second->allowed);
        $this->assertSame(30, $second->retryAfter);
    }

    public function test_clear_only_affects_specified_key(): void
    {
        $this->limiter->attempt('key:a', 2, 60);
        $this->limiter->attempt('key:a', 2, 60);
        $this->limiter->attempt('key:b', 2, 60);
        $this->limiter->attempt('key:b', 2, 60);

        $this->limiter->clear('key:a');

        $a = $this->limiter->attempt('key:a', 2, 60);
        $this->assertTrue($a->allowed);

        $b = $this->limiter->attempt('key:b', 2, 60);
        $this->assertFalse($b->allowed);
    }

    public function test_result_includes_correct_limit_value(): void
    {
        $result = $this->limiter->attempt('test', 100, 3600);

        $this->assertSame(100, $result->limit);
    }

    public function test_blocked_result_has_zero_remaining(): void
    {
        $key = 'zero';

        $this->limiter->attempt($key, 1, 60);

        $blocked = $this->limiter->attempt($key, 1, 60);
        $this->assertSame(0, $blocked->remaining);
    }
}
