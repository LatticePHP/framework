<?php

declare(strict_types=1);

namespace Lattice\Transport\Nats\Tests\Unit;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Transport\Nats\Testing\FakeNatsTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeNatsTransportTest extends TestCase
{
    private FakeNatsTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new FakeNatsTransport();
    }

    #[Test]
    public function publishStoresMessages(): void
    {
        $envelope = $this->createEnvelope('msg-1');

        $this->transport->publish($envelope, 'orders.created');

        $published = $this->transport->getPublishedOn('orders.created');
        $this->assertCount(1, $published);
        $this->assertSame($envelope, $published[0]);
    }

    #[Test]
    public function publishMultipleMessagesOnSameChannel(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1'), 'events');
        $this->transport->publish($this->createEnvelope('msg-2'), 'events');

        $this->assertCount(2, $this->transport->getPublishedOn('events'));
    }

    #[Test]
    public function publishOnDifferentChannels(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1'), 'channel-a');
        $this->transport->publish($this->createEnvelope('msg-2'), 'channel-b');

        $all = $this->transport->getPublished();
        $this->assertCount(1, $all['channel-a']);
        $this->assertCount(1, $all['channel-b']);
    }

    #[Test]
    public function subscribeReceivesPublishedMessages(): void
    {
        $received = [];

        $this->transport->subscribe('events', function (MessageEnvelopeInterface $envelope) use (&$received) {
            $received[] = $envelope;
        });

        $envelope = $this->createEnvelope('msg-1');
        $this->transport->publish($envelope, 'events');

        $this->assertCount(1, $received);
        $this->assertSame($envelope, $received[0]);
    }

    #[Test]
    public function subscribeOnlyReceivesMatchingChannel(): void
    {
        $received = [];

        $this->transport->subscribe('orders', function (MessageEnvelopeInterface $envelope) use (&$received) {
            $received[] = $envelope;
        });

        $this->transport->publish($this->createEnvelope('msg-1'), 'users');

        $this->assertCount(0, $received);
    }

    #[Test]
    public function acknowledgeTracksMessage(): void
    {
        $envelope = $this->createEnvelope('msg-ack-1');

        $this->assertFalse($this->transport->isAcknowledged('msg-ack-1'));

        $this->transport->acknowledge($envelope);

        $this->assertTrue($this->transport->isAcknowledged('msg-ack-1'));
    }

    #[Test]
    public function rejectTracksMessage(): void
    {
        $envelope = $this->createEnvelope('msg-rej-1');

        $this->assertFalse($this->transport->isRejected('msg-rej-1'));

        $this->transport->reject($envelope, requeue: true);

        $this->assertTrue($this->transport->isRejected('msg-rej-1'));
    }

    #[Test]
    public function assertPublishedPasses(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1'), 'events');

        // Should not throw
        $this->transport->assertPublished('events', 1);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assertPublishedFailsOnMismatch(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Expected 2 message(s) published on channel "events", got 1.');

        $this->transport->publish($this->createEnvelope('msg-1'), 'events');
        $this->transport->assertPublished('events', 2);
    }

    #[Test]
    public function assertNothingPublishedPasses(): void
    {
        $this->transport->assertNothingPublished();
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assertNothingPublishedFails(): void
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

        $this->transport->reset();

        $this->assertSame([], $this->transport->getPublished());
        $this->assertFalse($this->transport->isAcknowledged('msg-2'));
    }

    #[Test]
    public function getPublishedOnReturnsEmptyForUnknownChannel(): void
    {
        $this->assertSame([], $this->transport->getPublishedOn('nonexistent'));
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
