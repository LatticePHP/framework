<?php

declare(strict_types=1);

namespace Lattice\Observability\Logger;

final class InMemoryLogHandler implements LogHandlerInterface
{
    /** @var LogEntry[] */
    private array $entries = [];

    public function handle(LogEntry $entry): void
    {
        $this->entries[] = $entry;
    }

    /**
     * @return LogEntry[]
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function hasEntry(string $level, string $messagePattern): bool
    {
        foreach ($this->entries as $entry) {
            if ($entry->level === $level && preg_match($messagePattern, $entry->message)) {
                return true;
            }
        }
        return false;
    }
}
