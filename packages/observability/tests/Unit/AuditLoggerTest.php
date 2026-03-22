<?php

declare(strict_types=1);

namespace Lattice\Observability\Tests\Unit;

use Lattice\Observability\Audit\AuditEvent;
use Lattice\Observability\Audit\AuditLogger;
use Lattice\Observability\Logger\InMemoryLogHandler;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class AuditLoggerTest extends TestCase
{
    #[Test]
    public function it_logs_audit_event_to_handler(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new AuditLogger($handler);

        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
            timestamp: 1700000000.0,
        );

        $logger->log($event);

        $entries = $handler->getEntries();
        $this->assertCount(1, $entries);
    }

    #[Test]
    public function it_sets_audit_level(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new AuditLogger($handler);

        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
            timestamp: 1700000000.0,
        );

        $logger->log($event);

        $this->assertSame('audit', $handler->getEntries()[0]->level);
    }

    #[Test]
    public function it_formats_descriptive_message(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new AuditLogger($handler);

        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
            timestamp: 1700000000.0,
        );

        $logger->log($event);

        $entry = $handler->getEntries()[0];
        $this->assertStringContainsString('user-123', $entry->message);
        $this->assertStringContainsString('create', $entry->message);
        $this->assertStringContainsString('session-456', $entry->message);
    }

    #[Test]
    public function it_includes_event_data_in_context(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new AuditLogger($handler);

        $event = new AuditEvent(
            type: 'resource.update',
            actor: 'admin-1',
            target: 'doc-99',
            action: 'update',
            metadata: ['field' => 'title'],
            timestamp: 1700000000.0,
            correlationId: 'corr-abc',
        );

        $logger->log($event);

        $entry = $handler->getEntries()[0];
        $this->assertSame('resource.update', $entry->context['type']);
        $this->assertSame('admin-1', $entry->context['actor']);
        $this->assertSame('doc-99', $entry->context['target']);
        $this->assertSame('update', $entry->context['action']);
    }

    #[Test]
    public function it_preserves_correlation_id(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new AuditLogger($handler);

        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
            timestamp: 1700000000.0,
            correlationId: 'corr-xyz',
        );

        $logger->log($event);

        $this->assertSame('corr-xyz', $handler->getEntries()[0]->correlationId);
    }

    #[Test]
    public function it_uses_event_timestamp(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new AuditLogger($handler);

        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
            timestamp: 1700000000.0,
        );

        $logger->log($event);

        $this->assertSame(1700000000.0, $handler->getEntries()[0]->timestamp);
    }

    #[Test]
    public function it_generates_timestamp_when_event_has_zero(): void
    {
        $handler = new InMemoryLogHandler();
        $logger = new AuditLogger($handler);

        $event = new AuditEvent(
            type: 'user.login',
            actor: 'user-123',
            target: 'session-456',
            action: 'create',
        );

        $before = microtime(true);
        $logger->log($event);
        $after = microtime(true);

        $timestamp = $handler->getEntries()[0]->timestamp;
        $this->assertGreaterThanOrEqual($before, $timestamp);
        $this->assertLessThanOrEqual($after, $timestamp);
    }
}
