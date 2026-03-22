<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Recorders;

use DateTimeImmutable;
use DateTimeInterface;

final class ExceptionRecorder extends AbstractRecorder
{
    /** @var array<string, int> Exception counts by class */
    private array $counts = [];

    /** @var array<string, string> First seen timestamps by class */
    private array $firstSeen = [];

    /** @var array<string, string> Last seen timestamps by class */
    private array $lastSeen = [];

    /** @var array<string, list<int>> Rolling window of counts per interval for trend detection */
    private array $rollingCounts = [];

    /**
     * @param array<string, mixed> $data Expected keys: class, timestamp
     */
    public function record(array $data): void
    {
        if (!$this->isEnabled() || !$this->shouldSample()) {
            return;
        }

        $class = $data['class'] ?? 'Unknown';
        $timestamp = $data['timestamp'] ?? (new DateTimeImmutable())->format(DateTimeInterface::RFC3339_EXTENDED);

        $this->counts[$class] = ($this->counts[$class] ?? 0) + 1;

        if (!isset($this->firstSeen[$class])) {
            $this->firstSeen[$class] = $timestamp;
        }

        $this->lastSeen[$class] = $timestamp;

        // Track rolling counts for trend detection
        if (!isset($this->rollingCounts[$class])) {
            $this->rollingCounts[$class] = [];
        }
        $this->rollingCounts[$class][] = $this->counts[$class];
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetrics(): array
    {
        $exceptions = [];

        foreach ($this->counts as $class => $count) {
            $exceptions[$class] = [
                'count' => $count,
                'first_seen' => $this->firstSeen[$class] ?? null,
                'last_seen' => $this->lastSeen[$class] ?? null,
                'trend' => $this->detectTrend($class),
            ];
        }

        return [
            'total_exceptions' => array_sum($this->counts),
            'exceptions' => $exceptions,
        ];
    }

    public function reset(): void
    {
        $this->counts = [];
        $this->firstSeen = [];
        $this->lastSeen = [];
        $this->rollingCounts = [];
    }

    /**
     * Detect trend: increasing, decreasing, or stable.
     */
    private function detectTrend(string $class): string
    {
        $rolling = $this->rollingCounts[$class] ?? [];

        if (count($rolling) < 3) {
            return 'stable';
        }

        $recent = array_slice($rolling, -3);
        $isIncreasing = $recent[2] > $recent[1] && $recent[1] > $recent[0];
        $isDecreasing = $recent[2] < $recent[1] && $recent[1] < $recent[0];

        if ($isIncreasing) {
            return 'increasing';
        }

        if ($isDecreasing) {
            return 'decreasing';
        }

        return 'stable';
    }
}
