<?php

declare(strict_types=1);

namespace Lattice\Core\Http;

use Lattice\Http\Response;

final class ResponseEmitter
{
    /**
     * Send a Response to the client via PHP's built-in output functions.
     */
    public static function emit(Response $response): void
    {
        // Send status code
        http_response_code($response->statusCode);

        // Send headers
        foreach ($response->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Ensure JSON content type if not already set
        if (!isset($response->headers['Content-Type'])) {
            header('Content-Type: application/json; charset=utf-8');
        }

        // Send body
        $body = $response->body;
        if ($body !== null && $body !== '') {
            echo is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        }
    }
}
