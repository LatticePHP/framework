<?php

declare(strict_types=1);

namespace Lattice\Transport\Nats\Testing;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Messaging\TransportInterface;

final class FakeNatsTransport implements TransportInterface
{
    /** @var array<string, array<MessageEnvelopeInterface>> */
    private array $published = [];

    /** @var array<string, array<callable>> */
    private array $subscriptions = [];

    /** @var array<string, MessageEnvelopeInterface> */
    private array $acknowledged = [];

    /** @var array<string, array{envelope: MessageEnvelopeInterface, requeue: bool}> */
    private array $rejected = [];

    public function publish(MessageEnvelopeInterface $envelope, string $channel): void
    {
        $this->published[$channel][] = $envelope;

        // Deliver to any active subscriptions
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

    /** @return array<string, array<MessageEnvelopeInterface>> */
    public function getPublished(): array
    {
        return $this->published;
    }

    /**
     * @return array<MessageEnvelopeInterface>
     */
    public function getPublishedOn(string $channel): array
    {
        return $this->published[$channel] ?? [];
    }

    public function assertPublished(string $channel, int $expectedCount = 1): void
    {
        $actual = count($this->published[$channel] ?? []);

        if ($actual !== $expectedCount) {
            throw new \RuntimeException(
                sprintf(
                    'Expected %d message(s) published on channel "%s", got %d.',
                    $expectedCount,
                    $channel,
                    $actual,
                ),
            );
        }
    }

    public function assertNothingPublished(): void
    {
        $total = array_sum(array_map('count', $this->published));

        if ($total > 0) {
            throw new \RuntimeException(
                sprintf('Expected no published messages, but %d were published.', $total),
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
        $this->published = [];
        $this->subscriptions = [];
        $this->acknowledged = [];
        $this->rejected = [];
    }
}
