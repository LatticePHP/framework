<?php

declare(strict_types=1);

namespace Lattice\Observability\Logger;

final class DailyFileHandler implements LogHandlerInterface
{
    public function __construct(
        private readonly string $basePath,
        private readonly int $retentionDays = 14,
    ) {}

    public function handle(LogEntry $entry): void
    {
        $date = date('Y-m-d');
        $file = "{$this->basePath}-{$date}.log";
        $line = json_encode($entry->toArray(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";

        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }

    public function getRetentionDays(): int
    {
        return $this->retentionDays;
    }

    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Remove log files older than the retention period.
     *
     * @return int Number of files removed
     */
    public function pruneOldLogs(): int
    {
        $dir = dirname($this->basePath);
        $prefix = basename($this->basePath);

        if (!is_dir($dir)) {
            return 0;
        }

        $cutoff = strtotime("-{$this->retentionDays} days");
        $removed = 0;

        /** @var string $file */
        foreach (scandir($dir) as $file) {
            if (!str_starts_with($file, $prefix . '-') || !str_ends_with($file, '.log')) {
                continue;
            }

            // Extract date from filename: prefix-YYYY-MM-DD.log
            $datePart = substr($file, strlen($prefix) + 1, 10);
            $fileTime = strtotime($datePart);

            if ($fileTime !== false && $fileTime < $cutoff) {
                if (unlink($dir . DIRECTORY_SEPARATOR . $file)) {
                    $removed++;
                }
            }
        }

        return $removed;
    }
}
