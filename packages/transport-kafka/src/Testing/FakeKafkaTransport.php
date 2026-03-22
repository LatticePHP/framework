<?php

declare(strict_types=1);

namespace Lattice\Transport\Kafka\Testing;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Messaging\TransportInterface;

/**
 * In-memory Kafka fake with partition simulation.
 *
 * Messages are distributed across partitions using a simple hash
 * of the message's correlation ID. Each partition maintains FIFO order.
 */
final class FakeKafkaTransport implements TransportInterface
{
    private int $partitionCount;

    /** @var array<string, array<int, array<MessageEnvelopeInterface>>> topic -> partition -> messages */
    private array $partitions = [];

    /** @var array<string, array<callable>> */
    private array $subscriptions = [];

    /** @var array<string, MessageEnvelopeInterface> */
    private array $acknowledged = [];

    /** @var array<string, array{envelope: MessageEnvelopeInterface, requeue: bool}> */
    private array $rejected = [];

    /** @var array<string, array<int, int>> topic -> partition -> offset */
    private array $offsets = [];

    public function __construct(int $partitionCount = 3)
    {
        $this->partitionCount = $partitionCount;
    }

    public function publish(MessageEnvelopeInterface $envelope, string $channel): void
    {
        $partition = $this->resolvePartition($envelope);

        if (!isset($this->partitions[$channel])) {
            $this->partitions[$channel] = [];
        }

        $this->partitions[$channel][$partition][] = $envelope;

        // Deliver to subscribers
        foreach ($this->subscriptions[$channel] ?? [] as $handler) {
            $handler($envelope);
        }
    }

    public function subscribe(string $channel, callable $handler): void
    {
        $this->subscriptions[$channel][] = $handler;
    }

    public function acknowledge(MessageEnvelopeInterface $envelope): void
    {
        $this->acknowledged[$envelope->getMessageId()] = $envelope;
    }

    public function reject(MessageEnvelopeInterface $envelope, bool $requeue = false): void
    {
        $this->rejected[$envelope->getMessageId()] = [
            'envelope' => $envelope,
            'requeue' => $requeue,
        ];
    }

    /**
     * Consume the next message from a specific topic and partition.
     */
    public function consume(string $topic, int $partition): ?MessageEnvelopeInterface
    {
        $offset = $this->offsets[$topic][$partition] ?? 0;
        $messages = $this->partitions[$topic][$partition] ?? [];

        if (!isset($messages[$offset])) {
            return null;
        }

        $this->offsets[$topic][$partition] = $offset + 1;

        return $messages[$offset];
    }

    /**
     * Get the current offset for a topic/partition.
     */
    public function getOffset(string $topic, int $partition): int
    {
        return $this->offsets[$topic][$partition] ?? 0;
    }

    /**
     * Get the number of messages in a specific partition.
     */
    public function getPartitionDepth(string $topic, int $partition): int
    {
        return count($this->partitions[$topic][$partition] ?? []);
    }

    /**
     * Get the partition a message was assigned to.
     */
    public function resolvePartition(MessageEnvelopeInterface $envelope): int
    {
        $key = $envelope->getCorrelationId();

        return abs(crc32($key)) % $this->partitionCount;
    }

    /**
     * Get all messages across all partitions for a topic.
     *
     * @return array<MessageEnvelopeInterface>
     */
    public function getAllMessages(string $topic): array
    {
        $messages = [];

        foreach ($this->partitions[$topic] ?? [] as $partitionMessages) {
            foreach ($partitionMessages as $message) {
                $messages[] = $message;
            }
        }

        return $messages;
    }

    public function getTotalMessageCount(string $topic): int
    {
        return count($this->getAllMessages($topic));
    }

    /** @return array<int, array<MessageEnvelopeInterface>> */
    public function getPartitions(string $topic): array
    {
        return $this->partitions[$topic] ?? [];
    }

    public function getPartitionCount(): int
    {
        return $this->partitionCount;
    }

    public function assertPublished(string $topic, int $expectedCount = 1): void
    {
        $actual = $this->getTotalMessageCount($topic);

        if ($actual !== $expectedCount) {
            throw new \RuntimeException(
                sprintf(
                    'Expected %d message(s) on topic "%s", got %d.',
                    $expectedCount,
                    $topic,
                    $actual,
                ),
            );
        }
    }

    public function assertNothingPublished(): void
    {
        $total = 0;

        foreach ($this->partitions as $topicPartitions) {
            foreach ($topicPartitions as $messages) {
                $total += count($messages);
            }
        }

        if ($total > 0) {
            throw new \RuntimeException(
                sprintf('Expected no messages, but %d exist.', $total),
            );
        }
    }

    public function isAcknowledged(string $messageId): bool
    {
        return isset($this->acknowledged[$messageId]);
    }

    public function isRejected(string $messageId): bool
    {
        return isset($this->rejected[$messageId]);
    }

    public function reset(): void
    {
        $this->partitions = [];
        $this->subscriptions = [];
        $this->acknowledged = [];
        $this->rejected = [];
        $this->offsets = [];
    }
}
