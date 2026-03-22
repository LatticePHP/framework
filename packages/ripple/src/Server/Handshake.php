<?php

declare(strict_types=1);

namespace Lattice\Ripple\Server;

use RuntimeException;

/**
 * HTTP upgrade handshake for WebSocket connections per RFC 6455 Section 4.
 *
 * Validates the client's HTTP upgrade request and computes the
 * Sec-WebSocket-Accept response header.
 */
final class Handshake
{
    /** The magic GUID string used in the Sec-WebSocket-Accept computation (RFC 6455 Section 4.2.2). */
    private const WEBSOCKET_GUID = '258EAFA5-E914-47DA-95CA-5AB5DC11D3D7';

    private const REQUIRED_HEADERS = [
        'Upgrade',
        'Connection',
        'Sec-WebSocket-Key',
        'Sec-WebSocket-Version',
    ];

    /**
     * Parse raw HTTP request data into method, path, and headers.
     *
     * @return array{method: string, path: string, headers: array<string, string>}
     *
     * @throws RuntimeException If the request data cannot be parsed.
     */
    public static function parseRequest(string $data): array
    {
        $lines = explode("\r\n", $data);

        if (count($lines) < 2) {
            throw new RuntimeException('Malformed HTTP request: insufficient lines.');
        }

        $requestLine = explode(' ', $lines[0]);

        if (count($requestLine) < 3) {
            throw new RuntimeException('Malformed HTTP request line.');
        }

        $method = $requestLine[0];
        $path = $requestLine[1];

        $headers = [];
        for ($i = 1; $i < count($lines); $i++) {
            if ($lines[$i] === '') {
                break;
            }
            $colonPos = strpos($lines[$i], ':');
            if ($colonPos === false) {
                continue;
            }
            $key = trim(substr($lines[$i], 0, $colonPos));
            $value = trim(substr($lines[$i], $colonPos + 1));
            $headers[$key] = $value;
        }

        return [
            'method' => $method,
            'path' => $path,
            'headers' => $headers,
        ];
    }

    /**
     * Validate that the HTTP request is a proper WebSocket upgrade request.
     *
     * @param array<string, string> $headers
     *
     * @throws RuntimeException If validation fails.
     */
    public static function validate(array $headers): void
    {
        foreach (self::REQUIRED_HEADERS as $required) {
            $found = false;
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, $required) === 0) {
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                throw new RuntimeException(
                    sprintf('Missing required header: %s', $required),
                );
            }
        }

        $upgrade = self::getHeaderCaseInsensitive($headers, 'Upgrade');
        if (strtolower($upgrade) !== 'websocket') {
            throw new RuntimeException(
                sprintf('Invalid Upgrade header: expected "websocket", got "%s".', $upgrade),
            );
        }

        $connection = self::getHeaderCaseInsensitive($headers, 'Connection');
        if (stripos($connection, 'Upgrade') === false) {
            throw new RuntimeException(
                sprintf('Invalid Connection header: expected "Upgrade", got "%s".', $connection),
            );
        }

        $version = self::getHeaderCaseInsensitive($headers, 'Sec-WebSocket-Version');
        if ($version !== '13') {
            throw new RuntimeException(
                sprintf('Unsupported WebSocket version: expected "13", got "%s".', $version),
            );
        }

        $key = self::getHeaderCaseInsensitive($headers, 'Sec-WebSocket-Key');
        if (strlen(base64_decode($key, true) ?: '') !== 16) {
            throw new RuntimeException('Invalid Sec-WebSocket-Key: must be 16 bytes base64-encoded.');
        }
    }

    /**
     * Compute the Sec-WebSocket-Accept value from the client's Sec-WebSocket-Key.
     */
    public static function computeAcceptKey(string $clientKey): string
    {
        return base64_encode(sha1($clientKey . self::WEBSOCKET_GUID, true));
    }

    /**
     * Build the HTTP 101 Switching Protocols response.
     *
     * @param array<string, string> $headers The parsed request headers.
     */
    public static function buildResponse(array $headers): string
    {
        $clientKey = self::getHeaderCaseInsensitive($headers, 'Sec-WebSocket-Key');
        $acceptKey = self::computeAcceptKey($clientKey);

        return "HTTP/1.1 101 Switching Protocols\r\n"
            . "Upgrade: websocket\r\n"
            . "Connection: Upgrade\r\n"
            . "Sec-WebSocket-Accept: {$acceptKey}\r\n"
            . "\r\n";
    }

    /**
     * Build an HTTP error response for rejected connections.
     */
    public static function buildErrorResponse(int $statusCode, string $reason): string
    {
        $statusTexts = [
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            503 => 'Service Unavailable',
        ];

        $statusText = $statusTexts[$statusCode] ?? 'Error';

        return "HTTP/1.1 {$statusCode} {$statusText}\r\n"
            . "Content-Type: text/plain\r\n"
            . "Content-Length: " . strlen($reason) . "\r\n"
            . "Connection: close\r\n"
            . "\r\n"
            . $reason;
    }

    /**
     * Get a header value by case-insensitive key lookup.
     *
     * @param array<string, string> $headers
     */
    private static function getHeaderCaseInsensitive(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strcasecmp($key, $name) === 0) {
                return $value;
            }
        }

        return '';
    }
}
