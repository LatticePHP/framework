<?php

declare(strict_types=1);

namespace Lattice\Mcp\Tests\Protocol;

use Lattice\Mcp\Protocol\McpProtocolHandler;
use Lattice\Mcp\Protocol\SessionState;
use Lattice\Mcp\Registry\PromptRegistry;
use Lattice\Mcp\Registry\ResourceRegistry;
use Lattice\Mcp\Registry\ToolRegistry;
use Lattice\Mcp\Tests\Fixtures\ContactService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class McpProtocolHandlerTest extends TestCase
{
    private ToolRegistry $toolRegistry;
    private ResourceRegistry $resourceRegistry;
    private PromptRegistry $promptRegistry;
    private McpProtocolHandler $handler;

    protected function setUp(): void
    {
        $this->toolRegistry = new ToolRegistry();
        $this->resourceRegistry = new ResourceRegistry();
        $this->promptRegistry = new PromptRegistry();

        $this->toolRegistry->discover(ContactService::class);
        $this->resourceRegistry->discover(ContactService::class);
        $this->promptRegistry->discover(ContactService::class);

        $this->handler = new McpProtocolHandler(
            $this->toolRegistry,
            $this->resourceRegistry,
            $this->promptRegistry,
        );
    }

    #[Test]
    public function test_initialize_returns_capabilities(): void
    {
        $response = $this->sendRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertSame('2024-11-05', $response['result']['protocolVersion']);
        $this->assertSame('lattice-mcp', $response['result']['serverInfo']['name']);
        $this->assertArrayHasKey('tools', $response['result']['capabilities']);
        $this->assertArrayHasKey('resources', $response['result']['capabilities']);
        $this->assertArrayHasKey('prompts', $response['result']['capabilities']);
    }

    #[Test]
    public function test_reject_request_before_initialize(): void
    {
        $response = $this->sendRequest('tools/list');

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('not initialized', $response['error']['message']);
    }

    #[Test]
    public function test_ping_returns_empty_result(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('ping');

        $this->assertArrayHasKey('result', $response);
        $this->assertSame([], $response['result']);
    }

    #[Test]
    public function test_tools_list_returns_all_tools(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('tools/list');

        $this->assertArrayHasKey('result', $response);
        $toolNames = array_map(
            fn(array $t): string => $t['name'],
            $response['result']['tools'],
        );

        $this->assertContains('create_contact', $toolNames);
        $this->assertContains('search_contacts', $toolNames);
        $this->assertContains('deleteContact', $toolNames);
    }

    #[Test]
    public function test_tools_call_executes_tool(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('tools/call', [
            'name' => 'create_contact',
            'arguments' => [
                'firstName' => 'John',
                'lastName' => 'Doe',
                'email' => 'john@example.com',
            ],
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('content', $response['result']);
        $this->assertSame('text', $response['result']['content'][0]['type']);

        $decoded = json_decode($response['result']['content'][0]['text'], true);
        $this->assertSame('John', $decoded['firstName']);
    }

    #[Test]
    public function test_tools_call_unknown_tool(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('tools/call', [
            'name' => 'nonexistent',
            'arguments' => [],
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Unknown tool', $response['error']['message']);
    }

    #[Test]
    public function test_tools_call_missing_required_param(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('tools/call', [
            'name' => 'create_contact',
            'arguments' => ['firstName' => 'John'],
        ]);

        // Missing params returns either an error response or a result with isError
        $hasError = isset($response['error']) || ($response['result']['isError'] ?? false);
        $this->assertTrue($hasError);
    }

    #[Test]
    public function test_tools_call_with_defaults(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('tools/call', [
            'name' => 'search_contacts',
            'arguments' => ['query' => 'test'],
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('content', $response['result']);
        $this->assertArrayNotHasKey('isError', $response['result']);
    }

    #[Test]
    public function test_resources_list(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('resources/list');

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('resources', $response['result']);

        $uris = array_map(
            fn(array $r): string => $r['uri'],
            $response['result']['resources'],
        );

        $this->assertContains('contacts://{id}', $uris);
        $this->assertContains('config://app/settings', $uris);
    }

    #[Test]
    public function test_resources_read_static(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('resources/read', [
            'uri' => 'config://app/settings',
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('contents', $response['result']);
        $this->assertSame('config://app/settings', $response['result']['contents'][0]['uri']);
    }

    #[Test]
    public function test_resources_read_dynamic_template(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('resources/read', [
            'uri' => 'contacts://42',
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('contents', $response['result']);

        $content = json_decode($response['result']['contents'][0]['text'], true);
        $this->assertSame(42, $content['id']);
    }

    #[Test]
    public function test_resources_read_unknown(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('resources/read', [
            'uri' => 'unknown://foo',
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Unknown resource', $response['error']['message']);
    }

    #[Test]
    public function test_prompts_list(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('prompts/list');

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('prompts', $response['result']);

        $names = array_map(
            fn(array $p): string => $p['name'],
            $response['result']['prompts'],
        );

        $this->assertContains('summarize_contact', $names);
        $this->assertContains('draft_email', $names);
    }

    #[Test]
    public function test_prompts_get_renders_single_message(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('prompts/get', [
            'name' => 'summarize_contact',
            'arguments' => ['contactId' => '42'],
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertArrayHasKey('messages', $response['result']);
        $this->assertCount(1, $response['result']['messages']);
        $this->assertSame('user', $response['result']['messages'][0]['role']);
        $this->assertStringContainsString('42', $response['result']['messages'][0]['content']['text']);
    }

    #[Test]
    public function test_prompts_get_renders_multi_message(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('prompts/get', [
            'name' => 'draft_email',
            'arguments' => [
                'recipientName' => 'Alice',
                'subject' => 'Meeting tomorrow',
            ],
        ]);

        $this->assertArrayHasKey('result', $response);
        $this->assertCount(2, $response['result']['messages']);
        $this->assertSame('system', $response['result']['messages'][0]['role']);
        $this->assertSame('user', $response['result']['messages'][1]['role']);
        $this->assertStringContainsString('professional', $response['result']['messages'][0]['content']['text']);
    }

    #[Test]
    public function test_prompts_get_missing_required_argument(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('prompts/get', [
            'name' => 'summarize_contact',
            'arguments' => [],
        ]);

        $this->assertArrayHasKey('error', $response);
        $this->assertStringContainsString('Missing required argument', $response['error']['message']);
    }

    #[Test]
    public function test_unknown_method_returns_error(): void
    {
        $this->initializeSession();

        $response = $this->sendRequest('unknown/method');

        $this->assertArrayHasKey('error', $response);
        $this->assertSame(-32601, $response['error']['code']);
    }

    #[Test]
    public function test_notifications_return_empty(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        $response = $this->handler->processMessage((string) $json);

        $this->assertSame('', $response);
    }

    #[Test]
    public function test_session_state_transitions(): void
    {
        $this->assertSame(SessionState::Connecting, $this->handler->getState());

        // Initialize
        $this->sendRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
        ]);
        $this->assertSame(SessionState::Initializing, $this->handler->getState());

        // Send initialized notification
        $this->handler->processMessage((string) json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]));
        $this->assertSame(SessionState::Ready, $this->handler->getState());
        $this->assertTrue($this->handler->isReady());
    }

    #[Test]
    public function test_shutdown_lifecycle(): void
    {
        $this->initializeSession();

        // Shutdown
        $response = $this->sendRequest('shutdown');
        $this->assertArrayHasKey('result', $response);
        $this->assertSame(SessionState::ShuttingDown, $this->handler->getState());

        // After shutdown, requests are rejected
        $response = $this->sendRequest('tools/list');
        $this->assertArrayHasKey('error', $response);
    }

    #[Test]
    public function test_batch_request(): void
    {
        $this->initializeSession();

        $json = json_encode([
            ['jsonrpc' => '2.0', 'id' => 10, 'method' => 'ping'],
            ['jsonrpc' => '2.0', 'id' => 11, 'method' => 'tools/list'],
        ]);

        $response = $this->handler->processMessage((string) $json);
        $decoded = json_decode($response, true);

        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
    }

    #[Test]
    public function test_malformed_json(): void
    {
        $response = $this->handler->processMessage('not json');
        $decoded = json_decode($response, true);

        $this->assertArrayHasKey('error', $decoded);
        $this->assertSame(-32700, $decoded['error']['code']);
    }

    /**
     * Helper: initialize and ready the session.
     */
    private function initializeSession(): void
    {
        $this->sendRequest('initialize', [
            'protocolVersion' => '2024-11-05',
            'capabilities' => [],
            'clientInfo' => ['name' => 'test', 'version' => '1.0.0'],
        ]);

        $this->handler->processMessage((string) json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]));
    }

    /**
     * Helper: send a JSON-RPC request and decode the response.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function sendRequest(string $method, array $params = [], int $id = 1): array
    {
        static $nextId = 1;

        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => $nextId++,
            'method' => $method,
            'params' => $params,
        ]);

        $response = $this->handler->processMessage((string) $json);

        return (array) json_decode($response, true);
    }
}
