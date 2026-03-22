<?php

declare(strict_types=1);

namespace Lattice\Transport\Sqs;

final class SqsConfig
{
    public function __construct(
        public readonly string $region = 'us-east-1',
        public readonly string $queueUrl = '',
        public readonly ?string $accessKey = null,
        public readonly ?string $secretKey = null,
        public readonly ?string $endpoint = null,
    ) {}

    public function isFifoQueue(): bool
    {
        return str_ends_with($this->queueUrl, '.fifo');
    }

    public function isLocalStack(): bool
    {
        return $this->endpoint !== null;
    }
}
