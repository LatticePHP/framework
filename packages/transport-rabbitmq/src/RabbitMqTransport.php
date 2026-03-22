<?php

declare(strict_types=1);

namespace Lattice\Transport\RabbitMq;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Messaging\TransportInterface;

final class RabbitMqTransport implements TransportInterface
{
    public function __construct(
        private readonly RabbitMqConfig $config,
    ) {}

    public function getConfig(): RabbitMqConfig
    {
        return $this->config;
    }

    public function publish(MessageEnvelopeInterface $envelope, string $channel): void
    {
        throw new \RuntimeException(
            'RabbitMqTransport requires an AMQP client SDK. Use FakeRabbitMqTransport for testing.',
        );
    }

    public function subscribe(string $channel, callable $handler): void
    {
        throw new \RuntimeException(
            'RabbitMqTransport requires an AMQP client SDK. Use FakeRabbitMqTransport for testing.',
        );
    }

    public function acknowledge(MessageEnvelopeInterface $envelope): void
    {
        throw new \RuntimeException(
            'RabbitMqTransport requires an AMQP client SDK. Use FakeRabbitMqTransport for testing.',
        );
    }

    public function reject(MessageEnvelopeInterface $envelope, bool $requeue = false): void
    {
        throw new \RuntimeException(
            'RabbitMqTransport requires an AMQP client SDK. Use FakeRabbitMqTransport for testing.',
        );
    }
}
