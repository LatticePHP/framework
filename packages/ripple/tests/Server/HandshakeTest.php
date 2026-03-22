<?php

declare(strict_types=1);

namespace Lattice\Ripple\Tests\Server;

use Lattice\Ripple\Server\Handshake;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class HandshakeTest extends TestCase
{
    private function buildValidRequest(string $key = 'dGhlIHNhbXBsZSBub25jZQ=='): string
    {
        return "GET /chat HTTP/1.1\r\n"
            . "Host: server.example.com\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Key: {$key}\r\n"
            . "Sec-WebSocket-Version: 13\r\n"
            . "\r\n";
    }

    // --- parseRequest ---

    public function test_parse_valid_request(): void
    {
        $raw = $this->buildValidRequest();
        $result = Handshake::parseRequest($raw);

        $this->assertSame('GET', $result['method']);
        $this->assertSame('/chat', $result['path']);
        $this->assertSame('server.example.com', $result['headers']['Host']);
        $this->assertSame('websocket', $result['headers']['Upgrade']);
        $this->assertSame('Upgrade', $result['headers']['Connection']);
        $this->assertSame('dGhlIHNhbXBsZSBub25jZQ==', $result['headers']['Sec-WebSocket-Key']);
        $this->assertSame('13', $result['headers']['Sec-WebSocket-Version']);
    }

    public function test_parse_malformed_request_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed HTTP request');

        Handshake::parseRequest("BADREQUEST\r\n");
    }

    public function test_parse_malformed_request_line_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed HTTP request line');

        Handshake::parseRequest("BADLINE\r\nHost: test\r\n\r\n");
    }

    // --- validate ---

    public function test_validate_valid_headers(): void
    {
        $request = Handshake::parseRequest($this->buildValidRequest());

        // Should not throw
        Handshake::validate($request['headers']);
        $this->assertTrue(true);
    }

    public function test_validate_missing_upgrade_header(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required header: Upgrade');

        $headers = [
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
            'Sec-WebSocket-Version' => '13',
        ];

        Handshake::validate($headers);
    }

    public function test_validate_missing_connection_header(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required header: Connection');

        $headers = [
            'Upgrade' => 'websocket',
            'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
            'Sec-WebSocket-Version' => '13',
        ];

        Handshake::validate($headers);
    }

    public function test_validate_missing_websocket_key(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required header: Sec-WebSocket-Key');

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Version' => '13',
        ];

        Handshake::validate($headers);
    }

    public function test_validate_missing_version(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Missing required header: Sec-WebSocket-Version');

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
        ];

        Handshake::validate($headers);
    }

    public function test_validate_invalid_upgrade_value(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Upgrade header');

        $headers = [
            'Upgrade' => 'http',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
            'Sec-WebSocket-Version' => '13',
        ];

        Handshake::validate($headers);
    }

    public function test_validate_invalid_connection_value(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Connection header');

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'keep-alive',
            'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
            'Sec-WebSocket-Version' => '13',
        ];

        Handshake::validate($headers);
    }

    public function test_validate_unsupported_version(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported WebSocket version');

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Key' => 'dGhlIHNhbXBsZSBub25jZQ==',
            'Sec-WebSocket-Version' => '8',
        ];

        Handshake::validate($headers);
    }

    public function test_validate_invalid_key_length(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid Sec-WebSocket-Key');

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Key' => 'dG9vc2hvcnQ=', // 'tooshort' = 8 bytes, needs 16
            'Sec-WebSocket-Version' => '13',
        ];

        Handshake::validate($headers);
    }

    // --- computeAcceptKey ---

    public function test_compute_accept_key_matches_sha1_of_key_and_guid(): void
    {
        // Verify the accept key is SHA1(key + GUID) base64-encoded per RFC 6455
        $clientKey = 'dGhlIHNhbXBsZSBub25jZQ==';
        $guid = '258EAFA5-E914-47DA-95CA-5AB5DC11D3D7';
        $expected = base64_encode(sha1($clientKey . $guid, true));

        $this->assertSame($expected, Handshake::computeAcceptKey($clientKey));
    }

    public function test_compute_accept_key_deterministic(): void
    {
        $clientKey = 'dGhlIHNhbXBsZSBub25jZQ==';

        // Same input always produces the same output
        $result1 = Handshake::computeAcceptKey($clientKey);
        $result2 = Handshake::computeAcceptKey($clientKey);

        $this->assertSame($result1, $result2);
        $this->assertNotEmpty($result1);
    }

    public function test_compute_accept_key_different_keys_produce_different_results(): void
    {
        $key1 = base64_encode(random_bytes(16));
        $key2 = base64_encode(random_bytes(16));

        $this->assertNotSame(
            Handshake::computeAcceptKey($key1),
            Handshake::computeAcceptKey($key2),
        );
    }

    // --- buildResponse ---

    public function test_build_response_includes_switching_protocols(): void
    {
        $clientKey = 'dGhlIHNhbXBsZSBub25jZQ==';
        $expectedAccept = Handshake::computeAcceptKey($clientKey);

        $headers = [
            'Upgrade' => 'websocket',
            'Connection' => 'Upgrade',
            'Sec-WebSocket-Key' => $clientKey,
            'Sec-WebSocket-Version' => '13',
        ];

        $response = Handshake::buildResponse($headers);

        $this->assertStringStartsWith('HTTP/1.1 101 Switching Protocols', $response);
        $this->assertStringContainsString('Upgrade: websocket', $response);
        $this->assertStringContainsString('Connection: Upgrade', $response);
        $this->assertStringContainsString('Sec-WebSocket-Accept: ' . $expectedAccept, $response);
        $this->assertStringEndsWith("\r\n\r\n", $response);
    }

    // --- buildErrorResponse ---

    public function test_build_error_response(): void
    {
        $response = Handshake::buildErrorResponse(503, 'Server full');

        $this->assertStringStartsWith('HTTP/1.1 503 Service Unavailable', $response);
        $this->assertStringContainsString('Content-Type: text/plain', $response);
        $this->assertStringContainsString('Connection: close', $response);
        $this->assertStringEndsWith('Server full', $response);
    }

    public function test_build_error_response_400(): void
    {
        $response = Handshake::buildErrorResponse(400, 'Bad request');

        $this->assertStringStartsWith('HTTP/1.1 400 Bad Request', $response);
    }

    // --- Case-insensitive header handling ---

    public function test_validate_case_insensitive_headers(): void
    {
        $headers = [
            'upgrade' => 'websocket',
            'connection' => 'Upgrade',
            'sec-websocket-key' => 'dGhlIHNhbXBsZSBub25jZQ==',
            'sec-websocket-version' => '13',
        ];

        // Should not throw
        Handshake::validate($headers);
        $this->assertTrue(true);
    }
}
