<?php

declare(strict_types=1);

namespace Lattice\Transport\Kafka;

final class KafkaConfig
{
    /**
     * @param array<string> $brokers
     */
    public function __construct(
        public readonly array $brokers = ['localhost:9092'],
        public readonly string $groupId = 'default',
        public readonly string $topic = '',
        public readonly string $securityProtocol = 'plaintext',
    ) {}

    public function getBrokerList(): string
    {
        return implode(',', $this->brokers);
    }

    public function isSecure(): bool
    {
        return $this->securityProtocol !== 'plaintext';
    }
}
