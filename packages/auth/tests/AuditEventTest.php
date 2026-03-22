<?php

declare(strict_types=1);

namespace Lattice\Auth\Tests;

use Lattice\Auth\Events\AuditEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuditEventTest extends TestCase
{
    #[Test]
    public function it_creates_with_required_fields(): void
    {
        $event = new AuditEvent(
            type: 'login',
            principalId: 'user-123',
        );

        $this->assertSame('login', $event->type);
        $this->assertSame('user-123', $event->principalId);
        $this->assertInstanceOf(\DateTimeImmutable::class, $event->timestamp);
        $this->assertSame([], $event->metadata);
    }

    #[Test]
    public function it_creates_with_all_fields(): void
    {
        $timestamp = new \DateTimeImmutable('2026-01-01 00:00:00');
        $event = new AuditEvent(
            type: 'token_issued',
            principalId: 'user-456',
            timestamp: $timestamp,
            metadata: ['ip' => '127.0.0.1', 'user_agent' => 'TestAgent'],
        );

        $this->assertSame('token_issued', $event->type);
        $this->assertSame('user-456', $event->principalId);
        $this->assertSame($timestamp, $event->timestamp);
        $this->assertSame(['ip' => '127.0.0.1', 'user_agent' => 'TestAgent'], $event->metadata);
    }

    #[Test]
    public function it_supports_various_event_types(): void
    {
        $types = ['login', 'logout', 'token_issued', 'token_revoked', 'auth_failed'];

        foreach ($types as $type) {
            $event = new AuditEvent(type: $type, principalId: 'user-1');
            $this->assertSame($type, $event->type);
        }
    }

    #[Test]
    public function it_accepts_integer_principal_id(): void
    {
        $event = new AuditEvent(type: 'login', principalId: '42');

        $this->assertSame('42', $event->principalId);
    }

    #[Test]
    public function timestamp_defaults_to_now(): void
    {
        $before = new \DateTimeImmutable();
        $event = new AuditEvent(type: 'login', principalId: 'user-1');
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $event->timestamp);
        $this->assertLessThanOrEqual($after, $event->timestamp);
    }
}
