<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Execution;

final class ErrorFormatter
{
    public function __construct(
        private readonly bool $debug = false,
    ) {}

    /**
     * Format an exception as a GraphQL error object.
     *
     * @param array<int> $path
     * @return array<string, mixed>
     */
    public function format(
        \Throwable $exception,
        array $path = [],
    ): array {
        $error = [
            'message' => $this->debug ? $exception->getMessage() : $this->sanitizeMessage($exception),
        ];

        if (!empty($path)) {
            $error['path'] = $path;
        }

        if ($this->debug) {
            $error['extensions'] = [
                'debugMessage' => $exception->getMessage(),
                'exception' => get_class($exception),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $this->formatTrace($exception),
            ];
        }

        return $error;
    }

    /**
     * Format a simple error message (no exception).
     *
     * @param array<int> $path
     * @param array<array{line: int, column: int}> $locations
     * @return array<string, mixed>
     */
    public function formatMessage(
        string $message,
        array $path = [],
        array $locations = [],
    ): array {
        $error = ['message' => $message];

        if (!empty($path)) {
            $error['path'] = $path;
        }

        if (!empty($locations)) {
            $error['locations'] = $locations;
        }

        return $error;
    }

    /**
     * Format multiple errors from exceptions.
     *
     * @param array<\Throwable> $exceptions
     * @return array<array<string, mixed>>
     */
    public function formatAll(array $exceptions): array
    {
        return array_map(
            fn(\Throwable $e): array => $this->format($e),
            $exceptions,
        );
    }

    /**
     * Sanitize an exception message for production (hide internal details).
     */
    private function sanitizeMessage(\Throwable $exception): string
    {
        // Known safe exception types can expose their messages
        if ($exception instanceof GraphqlException) {
            return $exception->getMessage();
        }

        return 'Internal server error';
    }

    /**
     * Format an exception trace for debug output.
     *
     * @return array<array<string, mixed>>
     */
    private function formatTrace(\Throwable $exception): array
    {
        $trace = [];

        foreach ($exception->getTrace() as $entry) {
            $trace[] = [
                'file' => $entry['file'] ?? '<unknown>',
                'line' => $entry['line'] ?? 0,
                'call' => ($entry['class'] ?? '') . ($entry['type'] ?? '') . ($entry['function'] ?? ''),
            ];
        }

        return $trace;
    }
}
