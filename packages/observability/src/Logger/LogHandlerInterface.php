<?php

declare(strict_types=1);

namespace Lattice\Observability\Logger;

interface LogHandlerInterface
{
    public function handle(LogEntry $entry): void;
}
