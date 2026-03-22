<?php

declare(strict_types=1);

namespace Lattice\Transport\Nats;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Messaging\TransportInterface;

final class NatsTransport implements TransportInterface
{
    public function __construct(
        private readonly NatsConfig $config,
    ) {}

    public function getConfig(): NatsConfig
    {
        return $this->config;
    }

    public function publish(MessageEnvelopeInterface $envelope, string $channel): void
    {
        // Real implementation would use a NATS client SDK to publish
        // e.g., $this->client->publish($channel, serialize($envelope));
        throw new \RuntimeException(
            'NatsTransport requires a NATS client SDK. Use FakeNatsTransport for testing.',
        );
    }

    public function subscribe(string $channel, callable $handler): void
    {
        throw new \RuntimeException(
            'NatsTransport requires a NATS client SDK. Use FakeNatsTransport for testing.',
        );
    }

    public function acknowledge(MessageEnvelopeInterface $envelope): void
    {
        throw new \RuntimeException(
            'NatsTransport requires a NATS client SDK. Use FakeNatsTransport for testing.',
        );
    }

    public function reject(MessageEnvelopeInterface $envelope, bool $requeue = false): void
    {
        throw new \RuntimeException(
            'NatsTransport requires a NATS client SDK. Use FakeNatsTransport for testing.',
        );
    }
}
