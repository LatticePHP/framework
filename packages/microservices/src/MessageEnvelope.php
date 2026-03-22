<?php

declare(strict_types=1);

namespace Lattice\Microservices;

use DateTimeImmutable;
use Lattice\Contracts\Messaging\MessageEnvelopeInterface;

final class MessageEnvelope implements MessageEnvelopeInterface
{
    private readonly string $correlationId;
    private readonly DateTimeImmutable $timestamp;

    public function __construct(
        private readonly string $messageId,
        private readonly string $messageType,
        private readonly mixed $payload,
        private readonly string $schemaVersion = '1.0.0',
        ?string $correlationId = null,
        private readonly ?string $causationId = null,
        private readonly array $headers = [],
        ?DateTimeImmutable $timestamp = null,
        private readonly int $attempt = 1,
    ) {
        $this->correlationId = $correlationId ?? bin2hex(random_bytes(16));
        $this->timestamp = $timestamp ?? new DateTimeImmutable();
    }

    public function getMessageId(): string
    {
        return $this->messageId;
    }

    public function getMessageType(): string
    {
        return $this->messageType;
    }

    public function getSchemaVersion(): string
    {
        return $this->schemaVersion;
    }

    public function getCorrelationId(): string
    {
        return $this->correlationId;
    }

    public function getCausationId(): ?string
    {
        return $this->causationId;
    }

    public function getPayload(): mixed
    {
        return $this->payload;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getTimestamp(): DateTimeImmutable
    {
        return $this->timestamp;
    }

    public function getAttempt(): int
    {
        return $this->attempt;
    }
}
