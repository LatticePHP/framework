<?php

declare(strict_types=1);

namespace Lattice\Scheduler;

final class Schedule
{
    /** @var list<ScheduleEntry> */
    private array $entries = [];

    private int $callableCounter = 0;

    public function call(callable $task): ScheduleEntry
    {
        $this->callableCounter++;
        $entry = ScheduleEntry::forCallable($task, 'callable:' . $this->callableCounter);
        $this->entries[] = $entry;

        return $entry;
    }

    public function command(string $command): ScheduleEntry
    {
        $entry = ScheduleEntry::forCommand($command);
        $this->entries[] = $entry;

        return $entry;
    }

    public function job(string $jobClass): ScheduleEntry
    {
        $entry = ScheduleEntry::forJob($jobClass);
        $this->entries[] = $entry;

        return $entry;
    }

    /**
     * @return list<ScheduleEntry>
     */
    public function getEntries(): array
    {
        return $this->entries;
    }

    /**
     * @return list<ScheduleEntry>
     */
    public function getDueEntries(\DateTimeInterface $now): array
    {
        return array_values(
            array_filter($this->entries, fn (ScheduleEntry $entry): bool => $entry->isDue($now))
        );
    }
}
