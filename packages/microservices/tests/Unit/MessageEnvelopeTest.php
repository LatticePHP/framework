<?php

declare(strict_types=1);

namespace Lattice\Microservices\Tests\Unit;

use DateTimeImmutable;
use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Microservices\MessageEnvelope;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class MessageEnvelopeTest extends TestCase
{
    #[Test]
    public function it_implements_message_envelope_interface(): void
    {
        $envelope = new MessageEnvelope(
            messageId: 'msg-123',
            messageType: 'user.created',
            payload: ['name' => 'John'],
        );

        $this->assertInstanceOf(MessageEnvelopeInterface::class, $envelope);
    }

    #[Test]
    public function it_stores_and_returns_all_properties(): void
    {
        $timestamp = new DateTimeImmutable('2026-01-01T00:00:00Z');
        $envelope = new MessageEnvelope(
            messageId: 'msg-abc-123',
            messageType: 'order.placed',
            payload: ['orderId' => 42],
            schemaVersion: '2.0.0',
            correlationId: 'corr-xyz',
            causationId: 'cause-001',
            headers: ['x-tenant' => 'acme'],
            timestamp: $timestamp,
            attempt: 3,
        );

        $this->assertSame('msg-abc-123', $envelope->getMessageId());
        $this->assertSame('order.placed', $envelope->getMessageType());
        $this->assertSame(['orderId' => 42], $envelope->getPayload());
        $this->assertSame('2.0.0', $envelope->getSchemaVersion());
        $this->assertSame('corr-xyz', $envelope->getCorrelationId());
        $this->assertSame('cause-001', $envelope->getCausationId());
        $this->assertSame(['x-tenant' => 'acme'], $envelope->getHeaders());
        $this->assertSame($timestamp, $envelope->getTimestamp());
        $this->assertSame(3, $envelope->getAttempt());
    }

    #[Test]
    public function it_provides_sensible_defaults(): void
    {
        $envelope = new MessageEnvelope(
            messageId: 'msg-1',
            messageType: 'test.event',
            payload: null,
        );

        $this->assertSame('1.0.0', $envelope->getSchemaVersion());
        $this->assertNotEmpty($envelope->getCorrelationId());
        $this->assertNull($envelope->getCausationId());
        $this->assertSame([], $envelope->getHeaders());
        $this->assertInstanceOf(DateTimeImmutable::class, $envelope->getTimestamp());
        $this->assertSame(1, $envelope->getAttempt());
    }

    #[Test]
    public function it_generates_unique_correlation_id_when_not_provided(): void
    {
        $envelope1 = new MessageEnvelope(messageId: 'a', messageType: 't', payload: null);
        $envelope2 = new MessageEnvelope(messageId: 'b', messageType: 't', payload: null);

        $this->assertNotSame($envelope1->getCorrelationId(), $envelope2->getCorrelationId());
    }

    #[Test]
    public function it_accepts_null_payload(): void
    {
        $envelope = new MessageEnvelope(messageId: 'x', messageType: 'ping', payload: null);

        $this->assertNull($envelope->getPayload());
    }
}
