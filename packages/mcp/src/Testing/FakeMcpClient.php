<?php

declare(strict_types=1);

namespace Lattice\Mcp\Testing;

use Lattice\Mcp\Protocol\McpProtocolHandler;
use Lattice\Mcp\Registry\PromptRegistry;
use Lattice\Mcp\Registry\ResourceRegistry;
use Lattice\Mcp\Registry\ToolRegistry;

final class FakeMcpClient
{
    private readonly McpProtocolHandler $handler;
    private int $nextId = 1;

    /** @var list<array{method: string, params: array<string, mixed>}> */
    private array $invocations = [];

    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly ResourceRegistry $resourceRegistry = new ResourceRegistry(),
        private readonly PromptRegistry $promptRegistry = new PromptRegistry(),
    ) {
        $this->handler = new McpProtocolHandler(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
        );

        // Auto-initialize
        $this->sendRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'fake-client', 'version' => '1.0.0'],
        ]);
        $this->sendNotification('notifications/initialized');
    }

    /**
     * Call a tool by name with arguments.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function callTool(string $name, array $arguments = []): array
    {
        $this->invocations[] = ['method' => 'tools/call', 'params' => ['name' => $name, 'arguments' => $arguments]];

        return $this->sendRequest('tools/call', [
            'name' => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * Read a resource by URI.
     *
     * @return array<string, mixed>
     */
    public function readResource(string $uri): array
    {
        $this->invocations[] = ['method' => 'resources/read', 'params' => ['uri' => $uri]];

        return $this->sendRequest('resources/read', ['uri' => $uri]);
    }

    /**
     * Get a prompt by name with arguments.
     *
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    public function getPrompt(string $name, array $arguments = []): array
    {
        $this->invocations[] = ['method' => 'prompts/get', 'params' => ['name' => $name, 'arguments' => $arguments]];

        return $this->sendRequest('prompts/get', [
            'name' => $name,
            'arguments' => $arguments,
        ]);
    }

    /**
     * List all registered tools.
     *
     * @return list<array<string, mixed>>
     */
    public function listTools(): array
    {
        $result = $this->sendRequest('tools/list', []);

        return $result['result']['tools'] ?? [];
    }

    /**
     * List all registered resources.
     *
     * @return list<array<string, mixed>>
     */
    public function listResources(): array
    {
        $result = $this->sendRequest('resources/list', []);

        return $result['result']['resources'] ?? [];
    }

    /**
     * List all registered prompts.
     *
     * @return list<array<string, mixed>>
     */
    public function listPrompts(): array
    {
        $result = $this->sendRequest('prompts/list', []);

        return $result['result']['prompts'] ?? [];
    }

    /**
     * Assert a tool was called a specific number of times.
     */
    public function assertToolCalled(string $name, int $times = 1): void
    {
        $count = 0;

        foreach ($this->invocations as $invocation) {
            if ($invocation['method'] === 'tools/call' && ($invocation['params']['name'] ?? null) === $name) {
                $count++;
            }
        }

        if ($count !== $times) {
            throw new \PHPUnit\Framework\AssertionFailedError(
                sprintf('Expected tool "%s" to be called %d time(s), but was called %d time(s).', $name, $times, $count),
            );
        }
    }

    /**
     * Assert a tool was called with specific arguments.
     *
     * @param array<string, mixed> $arguments
     */
    public function assertToolCalledWith(string $name, array $arguments): void
    {
        foreach ($this->invocations as $invocation) {
            if (
                $invocation['method'] === 'tools/call'
                && ($invocation['params']['name'] ?? null) === $name
                && ($invocation['params']['arguments'] ?? []) === $arguments
            ) {
                return;
            }
        }

        throw new \PHPUnit\Framework\AssertionFailedError(
            sprintf(
                'Expected tool "%s" to be called with arguments %s, but no matching invocation found.',
                $name,
                json_encode($arguments),
            ),
        );
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function sendRequest(string $method, array $params): array
    {
        $id = $this->nextId++;

        $request = json_encode([
            'jsonrpc' => '2.0',
            'id' => $id,
            'method' => $method,
            'params' => $params,
        ], JSON_THROW_ON_ERROR);

        $response = $this->handler->processMessage($request);

        if ($response === '') {
            return [];
        }

        return (array) json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $params
     */
    private function sendNotification(string $method, array $params = []): void
    {
        $request = json_encode([
            'jsonrpc' => '2.0',
            'method' => $method,
            'params' => $params,
        ], JSON_THROW_ON_ERROR);

        $this->handler->processMessage($request);
    }
}
