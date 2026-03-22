<?php

declare(strict_types=1);

namespace Lattice\Transport\Sqs;

use Lattice\Contracts\Messaging\MessageEnvelopeInterface;
use Lattice\Contracts\Messaging\TransportInterface;

final class SqsTransport implements TransportInterface
{
    public function __construct(
        private readonly SqsConfig $config,
    ) {}

    public function getConfig(): SqsConfig
    {
        return $this->config;
    }

    public function publish(MessageEnvelopeInterface $envelope, string $channel): void
    {
        throw new \RuntimeException(
            'SqsTransport requires the AWS SDK. Use FakeSqsTransport for testing.',
        );
    }

    public function subscribe(string $channel, callable $handler): void
    {
        throw new \RuntimeException(
            'SqsTransport requires the AWS SDK. Use FakeSqsTransport for testing.',
        );
    }

    public function acknowledge(MessageEnvelopeInterface $envelope): void
    {
        throw new \RuntimeException(
            'SqsTransport requires the AWS SDK. Use FakeSqsTransport for testing.',
        );
    }

    public function reject(MessageEnvelopeInterface $envelope, bool $requeue = false): void
    {
        throw new \RuntimeException(
            'SqsTransport requires the AWS SDK. Use FakeSqsTransport for testing.',
        );
    }
}
