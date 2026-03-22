<?php

declare(strict_types=1);

namespace Lattice\Transport\Sqs\Testing;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Messaging\TransportInterface;

/**
 * In-memory SQS fake with FIFO behavior.
 *
 * Messages are stored in FIFO order per channel and delivered
 * to subscribers in the order they were published.
 */
final class FakeSqsTransport implements TransportInterface
{
    /** @var array<string, array<MessageEnvelopeInterface>> */
    private array $queues = [];

    /** @var array<string, array<callable>> */
    private array $subscriptions = [];

    /** @var array<string, MessageEnvelopeInterface> */
    private array $acknowledged = [];

    /** @var array<string, array{envelope: MessageEnvelopeInterface, requeue: bool}> */
    private array $rejected = [];

    /** @var array<string, MessageEnvelopeInterface> */
    private array $inflight = [];

    public function publish(MessageEnvelopeInterface $envelope, string $channel): void
    {
        $this->queues[$channel][] = $envelope;

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
        $messageId = $envelope->getMessageId();
        $this->acknowledged[$messageId] = $envelope;
        unset($this->inflight[$messageId]);
    }

    public function reject(MessageEnvelopeInterface $envelope, bool $requeue = false): void
    {
        $messageId = $envelope->getMessageId();
        $this->rejected[$messageId] = [
            'envelope' => $envelope,
            'requeue' => $requeue,
        ];
        unset($this->inflight[$messageId]);

        if ($requeue) {
            // Re-add to the back of all queues that contain this channel
            foreach ($this->queues as $channel => $messages) {
                $this->queues[$channel][] = $envelope;
                break; // SQS has a single queue per channel
            }
        }
    }

    /**
     * Receive the next message from a channel (FIFO order).
     * The message is placed in-flight until acknowledged or rejected.
     */
    public function receive(string $channel): ?MessageEnvelopeInterface
    {
        if (empty($this->queues[$channel])) {
            return null;
        }

        $envelope = array_shift($this->queues[$channel]);
        $this->inflight[$envelope->getMessageId()] = $envelope;

        return $envelope;
    }

    /** @return array<string, array<MessageEnvelopeInterface>> */
    public function getPublished(): array
    {
        // Return all messages ever published (not current queue state)
        return $this->queues;
    }

    /** @return array<MessageEnvelopeInterface> */
    public function getPublishedOn(string $channel): array
    {
        return $this->queues[$channel] ?? [];
    }

    public function getQueueDepth(string $channel): int
    {
        return count($this->queues[$channel] ?? []);
    }

    public function assertPublished(string $channel, int $expectedCount = 1): void
    {
        $actual = count($this->queues[$channel] ?? []);

        if ($actual !== $expectedCount) {
            throw new \RuntimeException(
                sprintf(
                    'Expected %d message(s) on channel "%s", got %d.',
                    $expectedCount,
                    $channel,
                    $actual,
                ),
            );
        }
    }

    public function assertNothingPublished(): void
    {
        $total = array_sum(array_map('count', $this->queues));

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

    public function isInflight(string $messageId): bool
    {
        return isset($this->inflight[$messageId]);
    }

    public function reset(): void
    {
        $this->queues = [];
        $this->subscriptions = [];
        $this->acknowledged = [];
        $this->rejected = [];
        $this->inflight = [];
    }
}
