<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Storage;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;

final class TimePartitioner
{
    public function __construct(
        private readonly string $basePath,
    ) {}

    public function pathForEntry(string $type, DateTimeInterface $timestamp): string
    {
        return sprintf(
            '%s/%s/%s/events.ndjson',
            $this->basePath,
            $type,
            $timestamp->format('Y/m/d/H'),
        );
    }

    public function pathForMetrics(DateTimeInterface $timestamp): string
    {
        return sprintf(
            '%s/metrics/%s/aggregates.json',
            $this->basePath,
            $timestamp->format('Y/m/d/H'),
        );
    }

    /**
     * @return list<string>
     */
    public function pathsForRange(DateTimeInterface $from, DateTimeInterface $to, string $type): array
    {
        $paths = [];
        $current = DateTimeImmutable::createFromInterface($from);
        $current = $current->setTime((int) $current->format('H'), 0, 0);
        $end = DateTimeImmutable::createFromInterface($to);

        while ($current <= $end) {
            $paths[] = $this->pathForEntry($type, $current);
            $current = $current->add(new DateInterval('PT1H'));
        }

        return $paths;
    }

    /**
     * @return list<string>
     */
    public function pathsForDay(DateTimeInterface $date, string $type): array
    {
        $day = DateTimeImmutable::createFromInterface($date)->setTime(0, 0, 0);
        $endOfDay = $day->setTime(23, 0, 0);

        return $this->pathsForRange($day, $endOfDay, $type);
    }

    /**
     * @return list<string>
     */
    public function directoryPathsOlderThan(DateTimeInterface $cutoff, string $type): array
    {
        $typePath = sprintf('%s/%s', $this->basePath, $type);

        if (!is_dir($typePath)) {
            return [];
        }

        $paths = [];
        $cutoffTimestamp = $cutoff->getTimestamp();

        $years = $this->listDirectories($typePath);
        foreach ($years as $year) {
            $yearPath = $typePath . '/' . $year;
            $months = $this->listDirectories($yearPath);

            foreach ($months as $month) {
                $monthPath = $yearPath . '/' . $month;
                $days = $this->listDirectories($monthPath);

                foreach ($days as $day) {
                    $dayPath = $monthPath . '/' . $day;
                    $hours = $this->listDirectories($dayPath);

                    foreach ($hours as $hour) {
                        $hourPath = $dayPath . '/' . $hour;
                        $dirTimestamp = mktime(
                            (int) $hour,
                            0,
                            0,
                            (int) $month,
                            (int) $day,
                            (int) $year,
                        );

                        if ($dirTimestamp !== false && $dirTimestamp < $cutoffTimestamp) {
                            $paths[] = $hourPath;
                        }
                    }
                }
            }
        }

        return $paths;
    }

    public function directoryForEntry(string $type, DateTimeInterface $timestamp): string
    {
        return sprintf(
            '%s/%s/%s',
            $this->basePath,
            $type,
            $timestamp->format('Y/m/d/H'),
        );
    }

    /**
     * @return list<string>
     */
    private function listDirectories(string $path): array
    {
        if (!is_dir($path)) {
            return [];
        }

        $dirs = [];
        $entries = scandir($path);

        if ($entries === false) {
            return [];
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            if (is_dir($path . '/' . $entry)) {
                $dirs[] = $entry;
            }
        }

        sort($dirs);

        return $dirs;
    }
}
