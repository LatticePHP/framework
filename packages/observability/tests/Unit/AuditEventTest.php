<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Unit;

use Lattice\Observability\Audit\AuditEvent;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuditEventTest extends TestCase
{
    #[Test]
    public function it_creates_event_with_required_fields(): void
    {
        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
        );

        $this->assertSame('user.login', $event->type);
        $this->assertSame('user-123', $event->actor);
        $this->assertSame('session-456', $event->target);
        $this->assertSame('create', $event->action);
    }

    #[Test]
    public function it_creates_event_with_all_fields(): void
    {
        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
            metadata: ['ip' => '127.0.0.1'],
            timestamp: 1700000000.0,
            correlationId: 'corr-789',
        );

        $this->assertSame(['ip' => '127.0.0.1'], $event->metadata);
        $this->assertSame(1700000000.0, $event->timestamp);
        $this->assertSame('corr-789', $event->correlationId);
    }

    #[Test]
    public function it_has_default_values_for_optional_fields(): void
    {
        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
        );

        $this->assertSame([], $event->metadata);
        $this->assertSame(0.0, $event->timestamp);
        $this->assertNull($event->correlationId);
    }

    #[Test]
    public function to_array_returns_all_set_fields(): void
    {
        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
            metadata: ['ip' => '127.0.0.1'],
            timestamp: 1700000000.0,
            correlationId: 'corr-789',
        );

        $array = $event->toArray();

        $this->assertSame('user.login', $array['type']);
        $this->assertSame('user-123', $array['actor']);
        $this->assertSame('session-456', $array['target']);
        $this->assertSame('create', $array['action']);
        $this->assertSame(['ip' => '127.0.0.1'], $array['metadata']);
        $this->assertSame(1700000000.0, $array['timestamp']);
        $this->assertSame('corr-789', $array['correlationId']);
    }

    #[Test]
    public function to_array_excludes_null_values(): void
    {
        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
        );

        $array = $event->toArray();

        $this->assertArrayNotHasKey('correlationId', $array);
        $this->assertArrayNotHasKey('metadata', $array);
    }

    #[Test]
    public function to_array_produces_valid_json(): void
    {
        $event = new AuditEvent(
            type: 'resource.update',
            actor: 'admin-1',
            target: 'doc-99',
            action: 'update',
            metadata: ['field' => 'title', 'old' => 'Draft', 'new' => 'Published'],
            timestamp: 1700000000.0,
            correlationId: 'corr-abc',
        );

        $json = json_encode($event->toArray());

        $this->assertIsString($json);
        $decoded = json_decode($json, true);
        $this->assertSame('resource.update', $decoded['type']);
        $this->assertSame('admin-1', $decoded['actor']);
    }
}
