<?php

declare(strict_types=1);

namespace Lattice\Transport\Kafka\Tests\Unit;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Transport\Kafka\Testing\FakeKafkaTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FakeKafkaTransportTest extends TestCase
{
    private FakeKafkaTransport $transport;

    protected function setUp(): void
    {
        $this->transport = new FakeKafkaTransport(partitionCount: 3);
    }

    #[Test]
    public function publishStoresMessages(): void
    {
        $envelope = $this->createEnvelope('msg-1', 'corr-1');

        $this->transport->publish($envelope, 'orders');

        $this->assertSame(1, $this->transport->getTotalMessageCount('orders'));
    }

    #[Test]
    public function publishDistributesAcrossPartitions(): void
    {
        // Publish messages with different correlation IDs to get different partitions
        for ($i = 0; $i < 20; $i++) {
            $this->transport->publish(
                $this->createEnvelope("msg-$i", "corr-$i"),
                'events',
            );
        }

        $partitions = $this->transport->getPartitions('events');
        $usedPartitions = count($partitions);

        // With 20 messages and 3 partitions, we should use at least 2 partitions
        $this->assertGreaterThanOrEqual(2, $usedPartitions);
        $this->assertSame(20, $this->transport->getTotalMessageCount('events'));
    }

    #[Test]
    public function sameCorrelationIdGoesToSamePartition(): void
    {
        $e1 = $this->createEnvelope('msg-1', 'same-corr');
        $e2 = $this->createEnvelope('msg-2', 'same-corr');

        $partition1 = $this->transport->resolvePartition($e1);
        $partition2 = $this->transport->resolvePartition($e2);

        $this->assertSame($partition1, $partition2);
    }

    #[Test]
    public function consumeReturnsMessagesInOrder(): void
    {
        $e1 = $this->createEnvelope('msg-1', 'same-key');
        $e2 = $this->createEnvelope('msg-2', 'same-key');

        $this->transport->publish($e1, 'topic');
        $this->transport->publish($e2, 'topic');

        $partition = $this->transport->resolvePartition($e1);

        $first = $this->transport->consume('topic', $partition);
        $second = $this->transport->consume('topic', $partition);
        $third = $this->transport->consume('topic', $partition);

        $this->assertSame('msg-1', $first->getMessageId());
        $this->assertSame('msg-2', $second->getMessageId());
        $this->assertNull($third);
    }

    #[Test]
    public function consumeTracksOffsets(): void
    {
        $envelope = $this->createEnvelope('msg-1', 'key');
        $partition = $this->transport->resolvePartition($envelope);

        $this->transport->publish($envelope, 'topic');

        $this->assertSame(0, $this->transport->getOffset('topic', $partition));

        $this->transport->consume('topic', $partition);

        $this->assertSame(1, $this->transport->getOffset('topic', $partition));
    }

    #[Test]
    public function consumeFromEmptyPartitionReturnsNull(): void
    {
        $this->assertNull($this->transport->consume('nonexistent', 0));
    }

    #[Test]
    public function subscribeReceivesMessages(): void
    {
        $received = [];

        $this->transport->subscribe('events', function (MessageEnvelopeInterface $envelope) use (&$received) {
            $received[] = $envelope;
        });

        $this->transport->publish($this->createEnvelope('msg-1', 'c-1'), 'events');

        $this->assertCount(1, $received);
    }

    #[Test]
    public function acknowledgeTracksMessage(): void
    {
        $envelope = $this->createEnvelope('msg-1', 'c-1');

        $this->assertFalse($this->transport->isAcknowledged('msg-1'));

        $this->transport->acknowledge($envelope);

        $this->assertTrue($this->transport->isAcknowledged('msg-1'));
    }

    #[Test]
    public function rejectTracksMessage(): void
    {
        $envelope = $this->createEnvelope('msg-1', 'c-1');

        $this->assertFalse($this->transport->isRejected('msg-1'));

        $this->transport->reject($envelope);

        $this->assertTrue($this->transport->isRejected('msg-1'));
    }

    #[Test]
    public function getPartitionDepth(): void
    {
        $envelope = $this->createEnvelope('msg-1', 'key');
        $partition = $this->transport->resolvePartition($envelope);

        $this->assertSame(0, $this->transport->getPartitionDepth('topic', $partition));

        $this->transport->publish($envelope, 'topic');

        $this->assertSame(1, $this->transport->getPartitionDepth('topic', $partition));
    }

    #[Test]
    public function getAllMessagesReturnsAllPartitions(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1', 'key-a'), 'topic');
        $this->transport->publish($this->createEnvelope('msg-2', 'key-b'), 'topic');
        $this->transport->publish($this->createEnvelope('msg-3', 'key-c'), 'topic');

        $all = $this->transport->getAllMessages('topic');
        $this->assertCount(3, $all);

        $ids = array_map(fn (MessageEnvelopeInterface $e) => $e->getMessageId(), $all);
        $this->assertContains('msg-1', $ids);
        $this->assertContains('msg-2', $ids);
        $this->assertContains('msg-3', $ids);
    }

    #[Test]
    public function getPartitionCountReturnsConfigured(): void
    {
        $this->assertSame(3, $this->transport->getPartitionCount());

        $transport5 = new FakeKafkaTransport(partitionCount: 5);
        $this->assertSame(5, $transport5->getPartitionCount());
    }

    #[Test]
    public function assertPublishedPasses(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1', 'c-1'), 'topic');

        $this->transport->assertPublished('topic', 1);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function assertPublishedThrowsOnMismatch(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->transport->assertPublished('topic', 1);
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

        $this->transport->publish($this->createEnvelope('msg-1', 'c-1'), 'topic');
        $this->transport->assertNothingPublished();
    }

    #[Test]
    public function resetClearsEverything(): void
    {
        $this->transport->publish($this->createEnvelope('msg-1', 'c-1'), 'topic');
        $this->transport->acknowledge($this->createEnvelope('msg-2', 'c-2'));

        $this->transport->reset();

        $this->assertSame(0, $this->transport->getTotalMessageCount('topic'));
        $this->assertFalse($this->transport->isAcknowledged('msg-2'));
        $this->assertSame([], $this->transport->getPartitions('topic'));
    }

    private function createEnvelope(string $messageId, string $correlationId): MessageEnvelopeInterface
    {
        $envelope = $this->createStub(MessageEnvelopeInterface::class);
        $envelope->method('getMessageId')->willReturn($messageId);
        $envelope->method('getMessageType')->willReturn('test.event');
        $envelope->method('getSchemaVersion')->willReturn('1.0');
        $envelope->method('getCorrelationId')->willReturn($correlationId);
        $envelope->method('getCausationId')->willReturn(null);
        $envelope->method('getPayload')->willReturn(['data' => $messageId]);
        $envelope->method('getHeaders')->willReturn([]);
        $envelope->method('getTimestamp')->willReturn(new \DateTimeImmutable());
        $envelope->method('getAttempt')->willReturn(1);

        return $envelope;
    }
}
