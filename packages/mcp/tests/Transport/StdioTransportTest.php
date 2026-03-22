<?php

declare(strict_types=1);

namespace Lattice\Mcp\Tests\Transport;

use Lattice\Mcp\Protocol\McpProtocolHandler;
use Lattice\Mcp\Registry\PromptRegistry;
use Lattice\Mcp\Registry\ResourceRegistry;
use Lattice\Mcp\Registry\ToolRegistry;
use Lattice\Mcp\Tests\Fixtures\ContactService;
use Lattice\Mcp\Transport\StdioTransport;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StdioTransportTest extends TestCase
{
    #[Test]
    public function test_stdio_processes_initialize_request(): void
    {
        $toolRegistry = new ToolRegistry();
        $toolRegistry->discover(ContactService::class);

        $handler = new McpProtocolHandler(
            $toolRegistry,
            new ResourceRegistry(),
            new PromptRegistry(),
        );

        $input = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => '2024-11-05',
                'capabilities' => [],
            ],
        ]) . "\n";

        $stdin = fopen('php://memory', 'r+');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');

        $this->assertNotFalse($stdin);
        $this->assertNotFalse($stdout);
        $this->assertNotFalse($stderr);

        fwrite($stdin, $input);
        rewind($stdin);

        $transport = new StdioTransport($handler, $stdin, $stdout, $stderr);
        $transport->start();

        rewind($stdout);
        $output = stream_get_contents($stdout);

        $this->assertNotFalse($output);
        $this->assertNotEmpty($output);

        $decoded = json_decode(trim((string) $output), true);
        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame(1, $decoded['id']);
        $this->assertArrayHasKey('result', $decoded);
        $this->assertSame('2024-11-05', $decoded['result']['protocolVersion']);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    #[Test]
    public function test_stdio_processes_multiple_messages(): void
    {
        $handler = new McpProtocolHandler(
            new ToolRegistry(),
            new ResourceRegistry(),
            new PromptRegistry(),
        );

        $messages = '';
        $messages .= json_encode(['jsonrpc' => '2.0', 'id' => 1, 'method' => 'initialize', 'params' => ['protocolVersion' => '2024-11-05', 'capabilities' => []]]) . "\n";
        $messages .= json_encode(['jsonrpc' => '2.0', 'method' => 'notifications/initialized']) . "\n";
        $messages .= json_encode(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping']) . "\n";

        $stdin = fopen('php://memory', 'r+');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');

        $this->assertNotFalse($stdin);
        $this->assertNotFalse($stdout);
        $this->assertNotFalse($stderr);

        fwrite($stdin, $messages);
        rewind($stdin);

        $transport = new StdioTransport($handler, $stdin, $stdout, $stderr);
        $transport->start();

        rewind($stdout);
        $output = stream_get_contents($stdout);
        $lines = array_filter(explode("\n", (string) $output), fn(string $l): bool => $l !== '');

        // Should have 2 responses (initialize response + ping response)
        // notifications/initialized produces no output
        $this->assertCount(2, $lines);

        $pingResponse = json_decode($lines[1], true);
        $this->assertSame(2, $pingResponse['id']);
        $this->assertSame([], $pingResponse['result']);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    #[Test]
    public function test_stdio_handles_eof(): void
    {
        $handler = new McpProtocolHandler(
            new ToolRegistry(),
            new ResourceRegistry(),
            new PromptRegistry(),
        );

        // Empty stdin — EOF immediately
        $stdin = fopen('php://memory', 'r+');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');

        $this->assertNotFalse($stdin);
        $this->assertNotFalse($stdout);
        $this->assertNotFalse($stderr);

        $transport = new StdioTransport($handler, $stdin, $stdout, $stderr);
        $transport->start();

        $this->assertFalse($transport->isRunning());

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    #[Test]
    public function test_stdio_ignores_empty_lines(): void
    {
        $handler = new McpProtocolHandler(
            new ToolRegistry(),
            new ResourceRegistry(),
            new PromptRegistry(),
        );

        $input = "\n\n" . json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => ['protocolVersion' => '2024-11-05', 'capabilities' => []],
        ]) . "\n\n";

        $stdin = fopen('php://memory', 'r+');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');

        $this->assertNotFalse($stdin);
        $this->assertNotFalse($stdout);
        $this->assertNotFalse($stderr);

        fwrite($stdin, $input);
        rewind($stdin);

        $transport = new StdioTransport($handler, $stdin, $stdout, $stderr);
        $transport->start();

        rewind($stdout);
        $output = stream_get_contents($stdout);
        $lines = array_filter(explode("\n", (string) $output), fn(string $l): bool => $l !== '');

        $this->assertCount(1, $lines);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }

    #[Test]
    public function test_stderr_gets_startup_message(): void
    {
        $handler = new McpProtocolHandler(
            new ToolRegistry(),
            new ResourceRegistry(),
            new PromptRegistry(),
        );

        $stdin = fopen('php://memory', 'r+');
        $stdout = fopen('php://memory', 'r+');
        $stderr = fopen('php://memory', 'r+');

        $this->assertNotFalse($stdin);
        $this->assertNotFalse($stdout);
        $this->assertNotFalse($stderr);

        $transport = new StdioTransport($handler, $stdin, $stdout, $stderr);
        $transport->start();

        rewind($stderr);
        $stderrOutput = stream_get_contents($stderr);

        $this->assertStringContainsString('Lattice MCP Server', (string) $stderrOutput);

        fclose($stdin);
        fclose($stdout);
        fclose($stderr);
    }
}
