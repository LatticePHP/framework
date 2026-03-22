<?php

declare(strict_types=1);

namespace Lattice\Scheduler;

final class ScheduleRunner
{
    private const DEFAULT_LOCK_TTL = 1440; // 24 hours in seconds

    public function __construct(
        private readonly ScheduleLock $lock,
    ) {}

    /**
     * Run all due tasks from the schedule.
     *
     * @return list<array{name: string, success: bool, result?: mixed, error?: string}>
     */
    public function run(Schedule $schedule, \DateTimeInterface $now): array
    {
        $results = [];

        foreach ($schedule->getDueEntries($now) as $entry) {
            if ($entry->isWithoutOverlapping()) {
                if (!$this->lock->acquire($entry->getName(), self::DEFAULT_LOCK_TTL)) {
                    continue;
                }
            }

            try {
                $result = $entry->execute();

                $results[] = [
                    'name' => $entry->getName(),
                    'success' => true,
                    'result' => $result,
                ];
            } catch (\Throwable $e) {
                // Release lock on failure so the task can be retried
                if ($entry->isWithoutOverlapping()) {
                    $this->lock->release($entry->getName());
                }

                $results[] = [
                    'name' => $entry->getName(),
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }
}
