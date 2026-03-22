<?php

declare(strict_types=1);

namespace Lattice\Observability\Logger;

final class MultiChannelHandler implements LogHandlerInterface
{
    /** @var array<string, LogHandlerInterface> */
    private array $handlers = [];

    public function addHandler(string $channel, LogHandlerInterface $handler): void
    {
        $this->handlers[$channel] = $handler;
    }

    public function removeHandler(string $channel): void
    {
        unset($this->handlers[$channel]);
    }

    public function hasHandler(string $channel): bool
    {
        return isset($this->handlers[$channel]);
    }

    /**
     * @return array<string, LogHandlerInterface>
     */
    public function getHandlers(): array
    {
        return $this->handlers;
    }

    public function handle(LogEntry $entry): void
    {
        foreach ($this->handlers as $handler) {
            $handler->handle($entry);
        }
    }
}
