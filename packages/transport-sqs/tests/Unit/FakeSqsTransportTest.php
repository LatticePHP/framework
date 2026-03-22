<?php

declare(strict_types=1);

namespace Lattice\Transport\Sqs\Tests\Unit;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Transport\Sqs\Testing\FakeSqsTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeSqsTransportTest extends TestCase
{
    private FakeSqsTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new FakeSqsTransport();
    }

    #[Test]
    public function publishStoresMessages(): void
    {
        $envelope = $this->createEnvelope('msg-1');

        $this->transport->publish($envelope, 'my-queue');

        $this->assertCount(1, $this->transport->getPublishedOn('my-queue'));
        $this->assertSame(1, $this->transport->getQueueDepth('my-queue'));
    }

    #[Test]
    public function publishMultipleMessagesPreservesOrder(): void
    {
        $e1 = $this->createEnvelope('msg-1');
        $e2 = $this->createEnvelope('msg-2');
        $e3 = $this->createEnvelope('msg-3');

        $this->transport->publish($e1, 'fifo-queue');
        $this->transport->publish($e2, 'fifo-queue');
        $this->transport->publish($e3, 'fifo-queue');

        $messages = $this->transport->getPublishedOn('fifo-queue');
        $this->assertSame('msg-1', $messages[0]->getMessageId());
        $this->assertSame('msg-2', $messages[1]->getMessageId());
        $this->assertSame('msg-3', $messages[2]->getMessageId());
    }

    #[Test]
    public function receiveReturnsFifoOrder(): void
    {
        $e1 = $this->createEnvelope('msg-1');
        $e2 = $this->createEnvelope('msg-2');

        $this->transport->publish($e1, 'queue');
        $this->transport->publish($e2, 'queue');

        $first = $this->transport->receive('queue');
        $second = $this->transport->receive('queue');
        $third = $this->transport->receive('queue');

        $this->assertSame('msg-1', $first->getMessageId());
        $this->assertSame('msg-2', $second->getMessageId());
        $this->assertNull($third);
    }

    #[Test]
    public function receiveMarksMessageAsInflight(): void
    {
        $envelope = $this->createEnvelope('msg-1');
        $this->transport->publish($envelope, 'queue');

        $received = $this->transport->receive('queue');

        $this->assertTrue($this->transport->isInflight('msg-1'));
    }

    #[Test]
    public function acknowledgeRemovesFromInflight(): void
    {
        $envelope = $this->createEnvelope('msg-1');
        $this->transport->publish($envelope, 'queue');

        $received = $this->transport->receive('queue');
        $this->transport->acknowledge($received);

        $this->assertTrue($this->transport->isAcknowledged('msg-1'));
        $this->assertFalse($this->transport->isInflight('msg-1'));
    }

    #[Test]
    public function rejectRemovesFromInflight(): void
    {
        $envelope = $this->createEnvelope('msg-1');
        $this->transport->publish($envelope, 'queue');

        $received = $this->transport->receive('queue');
        $this->transport->reject($received);

        $this->assertTrue($this->transport->isRejected('msg-1'));
        $this->assertFalse($this->transport->isInflight('msg-1'));
    }

    #[Test]
    public function subscribeReceivesMessages(): void
    {
        $received = [];

        $this->transport->subscribe('queue', function (MessageEnvelopeInterface $envelope) use (&$received) {
            $received[] = $envelope;
        });

        $this->transport->publish($this->createEnvelope('msg-1'), 'queue');

        $this->assertCount(1, $received);
    }

    #[Test]
    public function queueDepthReturnsCorrectCount(): void
    {
        $this->assertSame(0, $this->transport->getQueueDepth('empty-queue'));

        $this->transport->publish($this->createEnvelope('msg-1'), 'queue');
        $this->transport->publish($this->createEnvelope('msg-2'), 'queue');

        $this->assertSame(2, $this->transport->getQueueDepth('queue'));
    }

    #[Test]
    public function assertPublishedPasses(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1'), 'queue');
        $this->transport->publish($this->createEnvelope('msg-2'), 'queue');

        $this->transport->assertPublished('queue', 2);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assertPublishedThrowsOnMismatch(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->transport->assertPublished('queue', 1);
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

        $this->transport->publish($this->createEnvelope('msg-1'), 'queue');
        $this->transport->assertNothingPublished();
    }

    #[Test]
    public function resetClearsEverything(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1'), 'queue');
        $envelope = $this->transport->receive('queue');
        $this->transport->acknowledge($this->createEnvelope('msg-2'));

        $this->transport->reset();

        $this->assertSame(0, $this->transport->getQueueDepth('queue'));
        $this->assertFalse($this->transport->isAcknowledged('msg-2'));
        $this->assertFalse($this->transport->isInflight('msg-1'));
    }

    #[Test]
    public function receiveFromEmptyQueueReturnsNull(): void
    {
        $this->assertNull($this->transport->receive('nonexistent'));
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
