<?php

declare(strict_types=1);

namespace Lattice\Microservices\Transport;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Messaging\TransportInterface;

final class InMemoryTransport implements TransportInterface
{
    /** @var array<int, array{channel: string, envelope: MessageEnvelopeInterface}> */
    private array $published = [];

    /** @var array<string, array<callable>> */
    private array $subscriptions = [];

    /** @var array<string> */
    private array $acknowledged = [];

    /** @var array<int, array{messageId: string, requeue: bool}> */
    private array $rejected = [];

    public function publish(MessageEnvelopeInterface $envelope, string $channel): void
    {
        $this->published[] = [
            'channel' => $channel,
            'envelope' => $envelope,
        ];

        // Deliver to subscribers
        if (isset($this->subscriptions[$channel])) {
            foreach ($this->subscriptions[$channel] as $handler) {
                $handler($envelope);
            }
        }
    }

    public function subscribe(string $channel, callable $handler): void
    {
        $this->subscriptions[$channel][] = $handler;
    }

    public function acknowledge(MessageEnvelopeInterface $envelope): void
    {
        $this->acknowledged[] = $envelope->getMessageId();
    }

    public function reject(MessageEnvelopeInterface $envelope, bool $requeue = false): void
    {
        $this->rejected[] = [
            'messageId' => $envelope->getMessageId(),
            'requeue' => $requeue,
        ];
    }

    /** @return array<int, array{channel: string, envelope: MessageEnvelopeInterface}> */
    public function getPublished(): array
    {
        return $this->published;
    }

    /** @return array<string, array<callable>> */
    public function getSubscriptions(): array
    {
        return $this->subscriptions;
    }

    /** @return array<string> */
    public function getAcknowledged(): array
    {
        return $this->acknowledged;
    }

    /** @return array<int, array{messageId: string, requeue: bool}> */
    public function getRejected(): array
    {
        return $this->rejected;
    }
}
