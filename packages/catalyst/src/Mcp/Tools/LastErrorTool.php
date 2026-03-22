<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Mcp\Tools;

use Lattice\Catalyst\Mcp\McpToolInterface;

final class LastErrorTool implements McpToolInterface
{
    public function __construct(
        private readonly string $logPath = '',
    ) {}

    public function getName(): string
    {
        return 'last_error';
    }

    public function getDescription(): string
    {
        return 'Returns the latest exception/error from application logs with class, message, file, line, and stack trace';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => new \stdClass(),
            'required' => [],
        ];
    }

    public function execute(array $arguments): array
    {
        $logFile = $this->resolveLogFile();

        if ($logFile === null || !file_exists($logFile)) {
            return ['error' => 'No log file found', 'entry' => null];
        }

        $content = $this->readLastLines($logFile, 200);
        $entries = $this->parseLogEntries($content);

        // Find the last error/critical/emergency entry
        $lastError = null;

        foreach (array_reverse($entries) as $entry) {
            $level = strtolower($entry['level'] ?? '');

            if (in_array($level, ['error', 'critical', 'emergency', 'alert'], true)) {
                $lastError = $entry;
                break;
            }
        }

        if ($lastError === null) {
            return ['error' => 'No errors found in recent logs', 'entry' => null];
        }

        return ['entry' => $lastError];
    }

    private function resolveLogFile(): ?string
    {
        if ($this->logPath !== '' && file_exists($this->logPath)) {
            return $this->logPath;
        }

        // Try common log locations
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
            // Match typical log format: [2024-01-01 12:00:00] local.ERROR: Message
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
