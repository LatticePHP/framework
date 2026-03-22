<?php

declare(strict_types=1);

namespace Lattice\Observability\Audit;

use Lattice\Observability\Logger\LogEntry;
use Lattice\Observability\Logger\LogHandlerInterface;

final class AuditLogger
{
    public function __construct(
        private readonly LogHandlerInterface $handler,
    ) {}

    public function log(AuditEvent $event): void
    {
        $entry = new LogEntry(
            level: 'audit',
            message: sprintf('%s: %s performed %s on %s', $event->type, $event->actor, $event->action, $event->target),
            context: $event->toArray(),
            timestamp: $event->timestamp ?: microtime(true),
            correlationId: $event->correlationId,
        );

        $this->handler->handle($entry);
    }
}
