<?php

declare(strict_types=1);

namespace Lattice\Mcp\Transport;

use Lattice\Mcp\Protocol\McpProtocolHandler;

final class SseTransport implements TransportInterface
{
    private bool $running = false;
    private string $sessionId;

    /** @var resource|null */
    private mixed $outputStream;

    public function __construct(
        private readonly McpProtocolHandler $handler,
        private readonly int $heartbeatInterval = 30,
    ) {
        $this->sessionId = bin2hex(random_bytes(16));
        $this->outputStream = null;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }

    /**
     * Set the output stream for SSE events. Useful for testing.
     *
     * @param resource $stream
     */
    public function setOutputStream(mixed $stream): void
    {
        $this->outputStream = $stream;
    }

    public function start(): void
    {
        $this->running = true;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Process a JSON-RPC message received via HTTP POST and return the response.
     */
    public function handleMessage(string $json): string
    {
        return $this->handler->processMessage($json);
    }

    /**
     * Send an SSE event to the connected client.
     */
    public function sendEvent(string $data, string $event = 'message'): void
    {
        $output = $this->outputStream ?? null;

        if ($output === null) {
            return;
        }

        fwrite($output, "event: {$event}\n");
        fwrite($output, "data: {$data}\n\n");
        fflush($output);
    }

    /**
     * Send a heartbeat comment to keep the connection alive.
     */
    public function sendHeartbeat(): void
    {
        $output = $this->outputStream ?? null;

        if ($output === null) {
            return;
        }

        fwrite($output, ": heartbeat\n\n");
        fflush($output);
    }

    /**
     * Format SSE headers for the response.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Mcp-Session-Id' => $this->sessionId,
        ];
    }

    /**
     * Send the initial SSE endpoint event with the message URL.
     */
    public function sendEndpointEvent(string $messageUrl): void
    {
        $this->sendEvent($messageUrl, 'endpoint');
    }
}
