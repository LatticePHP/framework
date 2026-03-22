<?php

declare(strict_types=1);

namespace Lattice\Contracts\Messaging;

interface TransportInterface
{
    public function publish(MessageEnvelopeInterface $envelope, string $channel): void;

    public function subscribe(string $channel, callable $handler): void;

    public function acknowledge(MessageEnvelopeInterface $envelope): void;

    public function reject(MessageEnvelopeInterface $envelope, bool $requeue = false): void;
}
