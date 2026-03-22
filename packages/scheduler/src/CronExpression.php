<?php

declare(strict_types=1);

namespace Lattice\Scheduler;

final class CronExpression
{
    /**
     * @param list<string> $parts [minute, hour, day-of-month, month, day-of-week]
     */
    private function __construct(
        private readonly array $parts,
    ) {}

    public static function parse(string $expression): self
    {
        $parts = preg_split('/\s+/', trim($expression));

        if ($parts === false || count($parts) !== 5) {
            throw new \InvalidArgumentException(
                sprintf('Invalid cron expression: "%s". Expected 5 parts (minute hour day month weekday).', $expression)
            );
        }

        return new self($parts);
    }

    public function isDue(\DateTimeInterface $now): bool
    {
        $minute = (int) $now->format('i');
        $hour = (int) $now->format('G');
        $dayOfMonth = (int) $now->format('j');
        $month = (int) $now->format('n');
        $dayOfWeek = (int) $now->format('w'); // 0=Sunday

        return $this->matchField($this->parts[0], $minute, 0, 59)
            && $this->matchField($this->parts[1], $hour, 0, 23)
            && $this->matchField($this->parts[2], $dayOfMonth, 1, 31)
            && $this->matchField($this->parts[3], $month, 1, 12)
            && $this->matchDayOfWeek($this->parts[4], $dayOfWeek);
    }

    private function matchDayOfWeek(string $field, int $value): bool
    {
        if ($field === '*') {
            return true;
        }

        // Normalize: treat 7 as 0 (both mean Sunday) in the field
        $allowedValues = $this->expandField($field, 0, 7);

        // Map 7 -> 0 for Sunday
        $normalized = array_map(fn (int $v): int => $v === 7 ? 0 : $v, $allowedValues);

        return in_array($value, $normalized, true);
    }

    private function matchField(string $field, int $value, int $min, int $max): bool
    {
        if ($field === '*') {
            return true;
        }

        $allowedValues = $this->expandField($field, $min, $max);

        return in_array($value, $allowedValues, true);
    }

    /**
     * Expand a cron field into an array of allowed integer values.
     *
     * @return list<int>
     */
    private function expandField(string $field, int $min, int $max): array
    {
        $values = [];

        foreach (explode(',', $field) as $part) {
            if (str_contains($part, '/')) {
                // Step: */N or M-N/S
                [$range, $step] = explode('/', $part, 2);
                $step = (int) $step;

                if ($range === '*') {
                    $rangeStart = $min;
                    $rangeEnd = $max;
                } elseif (str_contains($range, '-')) {
                    [$rangeStart, $rangeEnd] = array_map('intval', explode('-', $range, 2));
                } else {
                    $rangeStart = (int) $range;
                    $rangeEnd = $max;
                }

                for ($i = $rangeStart; $i <= $rangeEnd; $i += $step) {
                    $values[] = $i;
                }
            } elseif (str_contains($part, '-')) {
                // Range: M-N
                [$rangeStart, $rangeEnd] = array_map('intval', explode('-', $part, 2));
                for ($i = $rangeStart; $i <= $rangeEnd; $i++) {
                    $values[] = $i;
                }
            } else {
                // Single value
                $values[] = (int) $part;
            }
        }

        return $values;
    }
}
