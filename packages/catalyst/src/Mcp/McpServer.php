<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Mcp;

use Lattice\Catalyst\Mcp\Tools\ApplicationInfoTool;
use Lattice\Catalyst\Mcp\Tools\ConfigReaderTool;
use Lattice\Catalyst\Mcp\Tools\DatabaseQueryTool;
use Lattice\Catalyst\Mcp\Tools\DatabaseSchemaTool;
use Lattice\Catalyst\Mcp\Tools\LastErrorTool;
use Lattice\Catalyst\Mcp\Tools\LogEntriesTool;
use Lattice\Catalyst\Mcp\Tools\ModuleGraphTool;
use Lattice\Catalyst\Mcp\Tools\RouteListTool;

final class McpServer
{
    private const string SERVER_NAME = 'lattice-catalyst';
    private const string SERVER_VERSION = '1.0.0';
    private const string PROTOCOL_VERSION = '2024-11-05';

    /** @var array<string, McpToolInterface> */
    private array $tools = [];

    private bool $initialized = false;
    private bool $running = false;

    /** @var resource|null */
    private mixed $stdin;

    /** @var resource|null */
    private mixed $stdout;

    /** @var resource|null */
    private mixed $stderr;

    public function __construct()
    {
        $this->stdin = null;
        $this->stdout = null;
        $this->stderr = null;
    }

    public function registerTool(McpToolInterface $tool): void
    {
        $this->tools[$tool->getName()] = $tool;
    }

    /**
     * Register all built-in tools with default configuration.
     *
     * @param array<string, mixed> $appInfo
     * @param array<int, array{method: string, uri: string, name: string|null, action: string, middleware: string[], guards: string[]}> $routes
     * @param array<string, array{imports: string[], exports: string[], providers: string[], controllers: string[]}> $modules
     * @param array<string, mixed> $config
     */
    public function registerBuiltinTools(
        array $appInfo = [],
        array $routes = [],
        array $modules = [],
        array $config = [],
        string $logPath = '',
    ): void {
        $this->registerTool(new ApplicationInfoTool($appInfo));
        $this->registerTool(new RouteListTool($routes));
        $this->registerTool(new ModuleGraphTool($modules));
        $this->registerTool(new LastErrorTool($logPath));
        $this->registerTool(new LogEntriesTool($logPath));
        $this->registerTool(new ConfigReaderTool($config));
        $this->registerTool(new DatabaseSchemaTool());
        $this->registerTool(new DatabaseQueryTool());
    }

    /**
     * @return array<string, McpToolInterface>
     */
    public function getTools(): array
    {
        return $this->tools;
    }

    /**
     * Process a single JSON-RPC 2.0 request and return the response.
     *
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function handleRequest(array $request): array
    {
        $jsonrpc = $request['jsonrpc'] ?? null;
        $method = $request['method'] ?? null;
        $id = $request['id'] ?? null;
        $params = $request['params'] ?? [];

        if ($jsonrpc !== '2.0') {
            return $this->errorResponse($id, -32600, 'Invalid Request: jsonrpc must be "2.0"');
        }

        if (!is_string($method)) {
            return $this->errorResponse($id, -32600, 'Invalid Request: method must be a string');
        }

        return match ($method) {
            'initialize' => $this->handleInitialize($id, $params),
            'notifications/initialized' => $this->handleInitializedNotification(),
            'tools/list' => $this->handleToolsList($id),
            'tools/call' => $this->handleToolsCall($id, $params),
            'ping' => $this->handlePing($id),
            default => $this->errorResponse($id, -32601, 'Method not found: ' . $method),
        };
    }

    /**
     * Process a raw JSON string and return a JSON response string.
     */
    public function processJsonRpc(string $json): string
    {
        $request = json_decode($json, true);

        if (!is_array($request)) {
            $response = $this->errorResponse(null, -32700, 'Parse error: invalid JSON');
            return (string) json_encode($response);
        }

        $response = $this->handleRequest($request);

        // Notifications don't get a response
        if ($response === []) {
            return '';
        }

        return (string) json_encode($response);
    }

    /**
     * Run the MCP server in stdio mode.
     * Reads JSON-RPC messages from stdin, writes responses to stdout.
     *
     * @param resource $stdin
     * @param resource $stdout
     * @param resource $stderr
     */
    public function run(mixed $stdin = null, mixed $stdout = null, mixed $stderr = null): void
    {
        $this->stdin = $stdin ?? STDIN;
        $this->stdout = $stdout ?? STDOUT;
        $this->stderr = $stderr ?? STDERR;
        $this->running = true;

        $this->writeStderr('Lattice Catalyst MCP Server v' . self::SERVER_VERSION);
        $this->writeStderr('Available tools: ' . count($this->tools));

        while ($this->running) {
            $line = fgets($this->stdin);

            if ($line === false) {
                break;
            }

            $line = trim($line);

            if ($line === '') {
                continue;
            }

            $response = $this->processJsonRpc($line);

            if ($response !== '') {
                $this->writeStdout($response);
            }
        }
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleInitialize(mixed $id, array $params): array
    {
        $this->initialized = true;

        return $this->successResponse($id, [
            'protocolVersion' => self::PROTOCOL_VERSION,
            'capabilities' => [
                'tools' => [
                    'listChanged' => false,
                ],
            ],
            'serverInfo' => [
                'name' => self::SERVER_NAME,
                'version' => self::SERVER_VERSION,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleInitializedNotification(): array
    {
        // Notification — no response
        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private function handlePing(mixed $id): array
    {
        return $this->successResponse($id, []);
    }

    /**
     * @return array<string, mixed>
     */
    private function handleToolsList(mixed $id): array
    {
        $tools = [];

        foreach ($this->tools as $tool) {
            $tools[] = [
                'name' => $tool->getName(),
                'description' => $tool->getDescription(),
                'inputSchema' => $tool->getInputSchema(),
            ];
        }

        return $this->successResponse($id, ['tools' => $tools]);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function handleToolsCall(mixed $id, array $params): array
    {
        $toolName = $params['name'] ?? null;
        $arguments = $params['arguments'] ?? [];

        if (!is_string($toolName)) {
            return $this->errorResponse($id, -32602, 'Invalid params: name must be a string');
        }

        if (!isset($this->tools[$toolName])) {
            return $this->errorResponse($id, -32602, 'Unknown tool: ' . $toolName);
        }

        $tool = $this->tools[$toolName];

        try {
            $result = $tool->execute(is_array($arguments) ? $arguments : []);

            return $this->successResponse($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => is_string($result) ? $result : (string) json_encode($result, JSON_PRETTY_PRINT),
                    ],
                ],
            ]);
        } catch (\Throwable $e) {
            return $this->successResponse($id, [
                'content' => [
                    [
                        'type' => 'text',
                        'text' => 'Error: ' . $e->getMessage(),
                    ],
                ],
                'isError' => true,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function successResponse(mixed $id, mixed $result): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'result' => $result,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function errorResponse(mixed $id, int $code, string $message): array
    {
        return [
            'jsonrpc' => '2.0',
            'id' => $id,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
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
