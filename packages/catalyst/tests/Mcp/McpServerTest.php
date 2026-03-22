<?php

declare(strict_types=1);

namespace Lattice\Catalyst\Tests\Mcp;

use Lattice\Catalyst\Mcp\McpServer;
use Lattice\Catalyst\Mcp\McpToolInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class McpServerTest extends TestCase
{
    #[Test]
    public function test_handle_initialize_request(): void
    {
        $server = new McpServer();

        $response = $server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [],
                'clientInfo' => [
                    'name' => 'test-client',
                    'version' => '1.0.0',
                ],
            ],
        ]);

        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertSame(1, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertSame('2024-11-05', $response['result']['protocolVersion']);
        $this->assertSame('lattice-catalyst', $response['result']['serverInfo']['name']);
        $this->assertTrue($server->isInitialized());
    }

    #[Test]
    public function test_handle_tools_list(): void
    {
        $server = new McpServer();
        $server->registerBuiltinTools();

        $response = $server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'tools/list',
        ]);

        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertSame(2, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('tools', $response['result']);
        $this->assertGreaterThanOrEqual(5, count($response['result']['tools']));

        $toolNames = array_map(
            fn(array $t): string => $t['name'],
            $response['result']['tools'],
        );

        $this->assertContains('app_info', $toolNames);
        $this->assertContains('route_list', $toolNames);
        $this->assertContains('module_graph', $toolNames);
        $this->assertContains('last_error', $toolNames);
        $this->assertContains('config_reader', $toolNames);
    }

    #[Test]
    public function test_handle_tools_call(): void
    {
        $server = new McpServer();
        $server->registerBuiltinTools(appInfo: [
            'framework_version' => '1.0.0',
            'environment' => 'testing',
        ]);

        $response = $server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => [
                'name' => 'app_info',
                'arguments' => [],
            ],
        ]);

        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertSame(3, $response['id']);
        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('content', $response['result']);
        $this->assertSame('text', $response['result']['content'][0]['type']);

        $text = $response['result']['content'][0]['text'];
        $decoded = json_decode($text, true);
        $this->assertSame('LatticePHP', $decoded['framework']);
    }

    #[Test]
    public function test_handle_unknown_tool(): void
    {
        $server = new McpServer();

        $response = $server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 4,
            'method' => 'tools/call',
            'params' => [
                'name' => 'nonexistent_tool',
                'arguments' => [],
            ],
        ]);

        $this->assertSame('2.0', $response['jsonrpc']);
        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32602, $response['error']['code']);
        $this->assertStringContainsString('Unknown tool', $response['error']['message']);
    }

    #[Test]
    public function test_handle_unknown_method(): void
    {
        $server = new McpServer();

        $response = $server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 5,
            'method' => 'unknown/method',
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32601, $response['error']['code']);
    }

    #[Test]
    public function test_handle_invalid_jsonrpc_version(): void
    {
        $server = new McpServer();

        $response = $server->handleRequest([
            'jsonrpc' => '1.0',
            'id' => 6,
            'method' => 'initialize',
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32600, $response['error']['code']);
    }

    #[Test]
    public function test_process_json_rpc_string(): void
    {
        $server = new McpServer();

        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 7,
            'method' => 'ping',
        ]);

        $response = $server->processJsonRpc((string) $json);
        $decoded = json_decode($response, true);

        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame(7, $decoded['id']);
        $this->assertArrayHasKey('result', $decoded);
    }

    #[Test]
    public function test_process_invalid_json(): void
    {
        $server = new McpServer();
        $response = $server->processJsonRpc('not valid json');
        $decoded = json_decode($response, true);

        $this->assertArrayHasKey('error', $decoded);
        $this->assertSame(-32700, $decoded['error']['code']);
    }

    #[Test]
    public function test_register_custom_tool(): void
    {
        $server = new McpServer();

        $tool = new class implements McpToolInterface {
            public function getName(): string
            {
                return 'custom_tool';
            }

            public function getDescription(): string
            {
                return 'A custom test tool';
            }

            public function getInputSchema(): array
            {
                return ['type' => 'object', 'properties' => new \stdClass()];
            }

            public function execute(array $arguments): array
            {
                return ['custom' => 'result'];
            }
        };

        $server->registerTool($tool);

        $this->assertArrayHasKey('custom_tool', $server->getTools());
    }

    #[Test]
    public function test_tool_execution_error_returns_is_error(): void
    {
        $server = new McpServer();

        $tool = new class implements McpToolInterface {
            public function getName(): string
            {
                return 'failing_tool';
            }

            public function getDescription(): string
            {
                return 'A tool that throws';
            }

            public function getInputSchema(): array
            {
                return ['type' => 'object', 'properties' => new \stdClass()];
            }

            public function execute(array $arguments): array
            {
                throw new \RuntimeException('Something went wrong');
            }
        };

        $server->registerTool($tool);

        $response = $server->handleRequest([
            'jsonrpc' => '2.0',
            'id' => 8,
            'method' => 'tools/call',
            'params' => [
                'name' => 'failing_tool',
                'arguments' => [],
            ],
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertTrue($response['result']['isError']);
        $this->assertStringContainsString('Something went wrong', $response['result']['content'][0]['text']);
    }

    #[Test]
    public function test_notifications_return_empty_response(): void
    {
        $server = new McpServer();

        $response = $server->handleRequest([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        $this->assertSame([], $response);
    }

    #[Test]
    public function test_stdio_run_and_stop(): void
    {
        $server = new McpServer();
        $server->registerBuiltinTools();

        // Create in-memory streams
        $stdinContent = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [],
        ]) . "\n";

        $stdin = fopen('php://memory', 'r+');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');

        $this->assertNotFalse($stdin);
        $this->assertNotFalse($stdout);
        $this->assertNotFalse($stderr);

        fwrite($stdin, $stdinContent);
        rewind($stdin);

        $server->run($stdin, $stdout, $stderr);

        rewind($stdout);
        $output = stream_get_contents($stdout);

        $this->assertNotFalse($output);
        $this->assertNotEmpty($output);

        $decoded = json_decode(trim($output), true);
        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertArrayHasKey('result', $decoded);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }
}
