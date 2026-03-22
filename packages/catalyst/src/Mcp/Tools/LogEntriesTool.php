<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Mcp\Tools;

use Lattice\Catalyst\Mcp\McpToolInterface;

final class LogEntriesTool implements McpToolInterface
{
    public function __construct(
        private readonly string $logPath = '',
    ) {}

    public function getName(): string
    {
        return 'log_entries';
    }

    public function getDescription(): string
    {
        return 'Returns the last N log entries with optional level filter (error, warning, info, debug)';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'count' => [
                    'type' => 'integer',
                    'description' => 'Number of entries to return (default: 50)',
                    'default' => 50,
                ],
                'level' => [
                    'type' => 'string',
                    'description' => 'Filter by log level (error, warning, info, debug)',
                    'enum' => ['error', 'warning', 'info', 'debug', 'critical', 'emergency', 'alert', 'notice'],
                ],
            ],
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $count = (int) ($arguments['count'] ?? 50);
        $levelFilter = $arguments['level'] ?? null;

        if ($count < 1) {
            $count = 50;
        }

        if ($count > 500) {
            $count = 500;
        }

        $logFile = $this->resolveLogFile();

        if ($logFile === null || !file_exists($logFile)) {
            return ['entries' => [], 'total' => 0];
        }

        $content = $this->readLastLines($logFile, $count * 5);
        $entries = $this->parseLogEntries($content);

        if (is_string($levelFilter) && $levelFilter !== '') {
            $entries = array_filter(
                $entries,
                fn(array $e): bool => strtolower($e['level']) === strtolower($levelFilter),
            );
            $entries = array_values($entries);
        }

        $entries = array_slice($entries, -$count);

        return [
            'entries' => $entries,
            'total' => count($entries),
        ];
    }

    private function resolveLogFile(): ?string
    {
        if ($this->logPath !== '' && is_file($this->logPath)) {
            return $this->logPath;
        }

        $candidates = [
            $this->logPath . '/lattice.log',
            $this->logPath . '/app.log',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function readLastLines(string $file, int $lines): string
    {
        $content = file_get_contents($file);

        if ($content === false) {
            return '';
        }

        $allLines = explode("\n", $content);
        $lastLines = array_slice($allLines, -$lines);

        return implode("\n", $lastLines);
    }

    /**
     * @return list<array{datetime: string, level: string, message: string, context: string}>
     */
    private function parseLogEntries(string $content): array
    {
        $entries = [];
        $lines = explode("\n", $content);
        $current = null;

        foreach ($lines as $line) {
            if (preg_match('/^\[(\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2})\]\s+\w+\.(\w+):\s+(.*)$/', $line, $matches)) {
                if ($current !== null) {
                    $entries[] = $current;
                }

                $current = [
                    'datetime' => $matches[1],
                    'level' => $matches[2],
                    'message' => $matches[3],
                    'context' => '',
                ];
            } elseif ($current !== null && trim($line) !== '') {
                $current['context'] .= $line . "\n";
            }
        }

        if ($current !== null) {
            $entries[] = $current;
        }

        return $entries;
    }
}
