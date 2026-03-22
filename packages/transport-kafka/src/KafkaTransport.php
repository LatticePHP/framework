<?php

declare(strict_types=1);

namespace Lattice\Transport\Kafka;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Messaging\TransportInterface;

final class KafkaTransport implements TransportInterface
{
    public function __construct(
        private readonly KafkaConfig $config,
    ) {}

    public function getConfig(): KafkaConfig
    {
        return $this->config;
    }

    public function publish(MessageEnvelopeInterface $envelope, string $channel): void
    {
        throw new \RuntimeException(
            'KafkaTransport requires the rdkafka extension. Use FakeKafkaTransport for testing.',
        );
    }

    public function subscribe(string $channel, callable $handler): void
    {
        throw new \RuntimeException(
            'KafkaTransport requires the rdkafka extension. Use FakeKafkaTransport for testing.',
        );
    }

    public function acknowledge(MessageEnvelopeInterface $envelope): void
    {
        throw new \RuntimeException(
            'KafkaTransport requires the rdkafka extension. Use FakeKafkaTransport for testing.',
        );
    }

    public function reject(MessageEnvelopeInterface $envelope, bool $requeue = false): void
    {
        throw new \RuntimeException(
            'KafkaTransport requires the rdkafka extension. Use FakeKafkaTransport for testing.',
        );
    }
}
