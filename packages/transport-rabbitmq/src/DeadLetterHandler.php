<?php

declare(strict_types=1);

namespace Lattice\Transport\RabbitMq;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Messaging\TransportInterface;

final class DeadLetterHandler
{
    private ?string $dlqExchange = null;
    private ?string $dlqQueue = null;

    /** @var array<array{envelope: MessageEnvelopeInterface, reason: \Throwable}> */
    private array $deadLetters = [];

    public function __construct(
        private readonly TransportInterface $transport,
    ) {}

    public function configure(string $dlqExchange, string $dlqQueue): void
    {
        $this->dlqExchange = $dlqExchange;
        $this->dlqQueue = $dlqQueue;
    }

    public function isConfigured(): bool
    {
        return $this->dlqExchange !== null && $this->dlqQueue !== null;
    }

    public function handleRejection(MessageEnvelopeInterface $envelope, \Throwable $reason): void
    {
        if (!$this->isConfigured()) {
            throw new \LogicException(
                'DeadLetterHandler is not configured. Call configure() before handling rejections.',
            );
        }

        $this->deadLetters[] = [
            'envelope' => $envelope,
            'reason' => $reason,
        ];

        // Reject without requeue (will go to DLQ)
        $this->transport->reject($envelope, requeue: false);

        // Publish to DLQ exchange
        $dlqChannel = $this->dlqExchange . '.' . $this->dlqQueue;
        $this->transport->publish($envelope, $dlqChannel);
    }

    /** @return array<array{envelope: MessageEnvelopeInterface, reason: \Throwable}> */
    public function getDeadLetters(): array
    {
        return $this->deadLetters;
    }

    public function getDeadLetterCount(): int
    {
        return count($this->deadLetters);
    }

    public function getDlqExchange(): ?string
    {
        return $this->dlqExchange;
    }

    public function getDlqQueue(): ?string
    {
        return $this->dlqQueue;
    }
}
