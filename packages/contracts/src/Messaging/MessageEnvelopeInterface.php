<?php

declare(strict_types=1);

namespace Lattice\Contracts\Messaging;

interface MessageEnvelopeInterface
{
    public function getMessageId(): string;

    public function getMessageType(): string;

    public function getSchemaVersion(): string;

    public function getCorrelationId(): string;

    public function getCausationId(): ?string;

    public function getPayload(): mixed;

    public function getHeaders(): array;

    public function getTimestamp(): \DateTimeImmutable;

    public function getAttempt(): int;
}
