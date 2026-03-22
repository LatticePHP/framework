<?php

declare(strict_types=1);

namespace Lattice\Transport\RabbitMq\Tests\Unit;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Transport\RabbitMq\Testing\FakeRabbitMqTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeRabbitMqTransportTest extends TestCase
{
    private FakeRabbitMqTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new FakeRabbitMqTransport();
    }

    #[Test]
    public function publishStoresMessages(): void
    {
        $envelope = $this->createEnvelope('msg-1');

        $this->transport->publish($envelope, 'orders');

        $published = $this->transport->getPublishedOn('orders');
        $this->assertCount(1, $published);
        $this->assertSame($envelope, $published[0]);
    }

    #[Test]
    public function publishMultipleMessages(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1'), 'events');
        $this->transport->publish($this->createEnvelope('msg-2'), 'events');

        $this->assertCount(2, $this->transport->getPublishedOn('events'));
    }

    #[Test]
    public function subscribeReceivesMessages(): void
    {
        $received = [];

        $this->transport->subscribe('orders', function (MessageEnvelopeInterface $envelope) use (&$received) {
            $received[] = $envelope;
        });

        $envelope = $this->createEnvelope('msg-1');
        $this->transport->publish($envelope, 'orders');

        $this->assertCount(1, $received);
        $this->assertSame($envelope, $received[0]);
    }

    #[Test]
    public function acknowledgeTracksMessage(): void
    {
        $envelope = $this->createEnvelope('msg-1');

        $this->assertFalse($this->transport->isAcknowledged('msg-1'));

        $this->transport->acknowledge($envelope);

        $this->assertTrue($this->transport->isAcknowledged('msg-1'));
    }

    #[Test]
    public function rejectTracksMessage(): void
    {
        $envelope = $this->createEnvelope('msg-1');

        $this->transport->reject($envelope, requeue: false);

        $this->assertTrue($this->transport->isRejected('msg-1'));
        $this->assertFalse($this->transport->wasRejectedWithRequeue('msg-1'));
    }

    #[Test]
    public function rejectWithRequeue(): void
    {
        $envelope = $this->createEnvelope('msg-1');

        $this->transport->reject($envelope, requeue: true);

        $this->assertTrue($this->transport->isRejected('msg-1'));
        $this->assertTrue($this->transport->wasRejectedWithRequeue('msg-1'));
    }

    #[Test]
    public function assertPublishedPasses(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1'), 'events');

        $this->transport->assertPublished('events', 1);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assertPublishedThrowsOnMismatch(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->transport->assertPublished('events', 1);
    }

    #[Test]
    public function assertNothingPublishedPasses(): void
    {
        $this->transport->assertNothingPublished();
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assertNothingPublishedThrows(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->transport->publish($this->createEnvelope('msg-1'), 'events');
        $this->transport->assertNothingPublished();
    }

    #[Test]
    public function resetClearsEverything(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1'), 'events');
        $this->transport->acknowledge($this->createEnvelope('msg-2'));
        $this->transport->reject($this->createEnvelope('msg-3'));

        $this->transport->reset();

        $this->assertSame([], $this->transport->getPublished());
        $this->assertFalse($this->transport->isAcknowledged('msg-2'));
        $this->assertFalse($this->transport->isRejected('msg-3'));
    }

    private function createEnvelope(string $messageId): MessageEnvelopeInterface
    {
        $envelope = $this->createStub(MessageEnvelopeInterface::class);
        $envelope->method('getMessageId')->willReturn($messageId);
        $envelope->method('getMessageType')->willReturn('test.event');
        $envelope->method('getSchemaVersion')->willReturn('1.0');
        $envelope->method('getCorrelationId')->willReturn('corr-' . $messageId);
        $envelope->method('getCausationId')->willReturn(null);
        $envelope->method('getPayload')->willReturn(['data' => $messageId]);
        $envelope->method('getHeaders')->willReturn([]);
        $envelope->method('getTimestamp')->willReturn(new \DateTimeImmutable());
        $envelope->method('getAttempt')->willReturn(1);

        return $envelope;
    }
}
