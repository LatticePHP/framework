<?php

declare(strict_types=1);

namespace Lattice\Mcp\Transport;

use Lattice\Mcp\Protocol\McpProtocolHandler;

final class StdioTransport implements TransportInterface
{
    private bool $running = false;

    /** @var resource|null */
    private mixed $stdin;

    /** @var resource|null */
    private mixed $stdout;

    /** @var resource|null */
    private mixed $stderr;

    /**
     * @param resource|null $stdin
     * @param resource|null $stdout
     * @param resource|null $stderr
     */
    public function __construct(
        private readonly McpProtocolHandler $handler,
        mixed $stdin = null,
        mixed $stdout = null,
        mixed $stderr = null,
    ) {
        $this->stdin = $stdin;
        $this->stdout = $stdout;
        $this->stderr = $stderr;
    }

    public function start(): void
    {
        $this->stdin ??= STDIN;
        $this->stdout ??= STDOUT;
        $this->stderr ??= STDERR;
        $this->running = true;

        $this->writeStderr('Lattice MCP Server started (stdio transport)');

        $buffer = '';

        while ($this->running) {
            $line = fgets($this->stdin);

            if ($line === false) {
                // EOF — stdin closed
                break;
            }

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $buffer .= $line;

            // Attempt to parse — buffer partial reads
            $decoded = json_decode($buffer, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                // Could be a partial read, continue buffering
                // But if it starts with something clearly invalid, flush buffer
                if (!str_starts_with(ltrim($buffer), '{') && !str_starts_with(ltrim($buffer), '[')) {
                    $buffer = '';
                }

                continue;
            }

            $response = $this->handler->processMessage($buffer);
            $buffer = '';

            if ($response !== '') {
                $this->writeStdout($response);
            }
        }

        $this->running = false;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    private function writeStdout(string $message): void
    {
        if ($this->stdout !== null) {
            fwrite($this->stdout, $message . "\n");
            fflush($this->stdout);
        }
    }

    private function writeStderr(string $message): void
    {
        if ($this->stderr !== null) {
            fwrite($this->stderr, $message . "\n");
            fflush($this->stderr);
        }
    }
}
