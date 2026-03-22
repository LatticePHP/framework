<?php

declare(strict_types=1);

namespace Lattice\Mcp\Tests\Protocol;

use Lattice\Mcp\Protocol\JsonRpcException;
use Lattice\Mcp\Protocol\JsonRpcRequest;
use Lattice\Mcp\Protocol\JsonRpcResponse;
use Lattice\Mcp\Protocol\JsonRpcServer;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class JsonRpcServerTest extends TestCase
{
    private JsonRpcServer $server;

    protected function setUp(): void
    {
        $this->server = new JsonRpcServer();
    }

    #[Test]
    public function test_parse_valid_request(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => [],
        ]);

        $request = $this->server->parse((string) $json);

        $this->assertInstanceOf(JsonRpcRequest::class, $request);
        $this->assertSame('tools/list', $request->method);
        $this->assertSame(1, $request->id);
        $this->assertSame([], $request->params);
        $this->assertFalse($request->isNotification());
    }

    #[Test]
    public function test_parse_notification_no_id(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'method' => 'notifications/initialized',
        ]);

        $request = $this->server->parse((string) $json);

        $this->assertInstanceOf(JsonRpcRequest::class, $request);
        $this->assertNull($request->id);
        $this->assertTrue($request->isNotification());
    }

    #[Test]
    public function test_parse_batch_request(): void
    {
        $json = json_encode([
            ['jsonrpc' => '2.0', 'id' => 1, 'method' => 'tools/list'],
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'ping'],
        ]);

        $requests = $this->server->parse((string) $json);

        $this->assertIsArray($requests);
        $this->assertCount(2, $requests);
        $this->assertSame('tools/list', $requests[0]->method);
        $this->assertSame('ping', $requests[1]->method);
    }

    #[Test]
    public function test_parse_invalid_json_throws(): void
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionCode(JsonRpcException::PARSE_ERROR);

        $this->server->parse('not valid json');
    }

    #[Test]
    public function test_parse_empty_batch_throws(): void
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionCode(JsonRpcException::INVALID_REQUEST);

        $this->server->parse('[]');
    }

    #[Test]
    public function test_parse_wrong_jsonrpc_version_throws(): void
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionCode(JsonRpcException::INVALID_REQUEST);

        $json = json_encode([
            'jsonrpc' => '1.0',
            'id' => 1,
            'method' => 'test',
        ]);

        $this->server->parse((string) $json);
    }

    #[Test]
    public function test_parse_missing_method_throws(): void
    {
        $this->expectException(JsonRpcException::class);
        $this->expectExceptionCode(JsonRpcException::INVALID_REQUEST);

        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
        ]);

        $this->server->parse((string) $json);
    }

    #[Test]
    public function test_parse_request_with_string_id(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 'abc-123',
            'method' => 'ping',
        ]);

        $request = $this->server->parse((string) $json);

        $this->assertInstanceOf(JsonRpcRequest::class, $request);
        $this->assertSame('abc-123', $request->id);
    }

    #[Test]
    public function test_parse_request_with_params(): void
    {
        $json = json_encode([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/call',
            'params' => ['name' => 'my_tool', 'arguments' => ['key' => 'value']],
        ]);

        $request = $this->server->parse((string) $json);

        $this->assertInstanceOf(JsonRpcRequest::class, $request);
        $this->assertSame('my_tool', $request->params['name']);
    }

    #[Test]
    public function test_encode_success_response(): void
    {
        $response = JsonRpcResponse::success(1, ['tools' => []]);
        $json = $this->server->encode($response);

        $decoded = json_decode($json, true);

        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame(1, $decoded['id']);
        $this->assertArrayHasKey('result', $decoded);
        $this->assertSame([], $decoded['result']['tools']);
    }

    #[Test]
    public function test_encode_error_response(): void
    {
        $response = JsonRpcResponse::error(1, -32601, 'Method not found');
        $json = $this->server->encode($response);

        $decoded = json_decode($json, true);

        $this->assertSame('2.0', $decoded['jsonrpc']);
        $this->assertSame(1, $decoded['id']);
        $this->assertArrayHasKey('error', $decoded);
        $this->assertSame(-32601, $decoded['error']['code']);
        $this->assertSame('Method not found', $decoded['error']['message']);
    }

    #[Test]
    public function test_encode_batch_responses(): void
    {
        $responses = [
            JsonRpcResponse::success(1, []),
            JsonRpcResponse::success(2, ['data' => 'test']),
        ];

        $json = $this->server->encodeBatch($responses);
        $decoded = json_decode($json, true);

        $this->assertIsArray($decoded);
        $this->assertCount(2, $decoded);
        $this->assertSame(1, $decoded[0]['id']);
        $this->assertSame(2, $decoded[1]['id']);
    }

    #[Test]
    public function test_response_is_error(): void
    {
        $success = JsonRpcResponse::success(1, []);
        $error = JsonRpcResponse::error(1, -32600, 'Error');

        $this->assertFalse($success->isError());
        $this->assertTrue($error->isError());
    }

    #[Test]
    public function test_error_response_with_data(): void
    {
        $response = JsonRpcResponse::error(1, -32602, 'Invalid params', ['field' => 'name']);
        $arr = $response->toArray();

        $this->assertSame(['field' => 'name'], $arr['error']['data']);
    }

    #[Test]
    public function test_json_rpc_exception_to_response(): void
    {
        $exception = JsonRpcException::methodNotFound('unknown', 42);
        $response = $exception->toResponse();

        $this->assertTrue($response->isError());
        $arr = $response->toArray();
        $this->assertSame(42, $arr['id']);
        $this->assertSame(-32601, $arr['error']['code']);
        $this->assertStringContainsString('unknown', $arr['error']['message']);
    }
}
