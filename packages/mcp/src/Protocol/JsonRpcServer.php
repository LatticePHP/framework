<?php

declare(strict_types=1);

namespace Lattice\Mcp\Protocol;

final class JsonRpcServer
{
    /**
     * Parse a JSON string into one or more JsonRpcRequest objects.
     *
     * @return JsonRpcRequest|list<JsonRpcRequest>
     * @throws JsonRpcException
     */
    public function parse(string $json): JsonRpcRequest|array
    {
        $data = json_decode($json, true);

        if (!is_array($data)) {
            throw JsonRpcException::parseError('Invalid JSON');
        }

        // Batch request
        if (array_is_list($data)) {
            if ($data === []) {
                throw JsonRpcException::invalidRequest('Empty batch');
            }

            return array_map($this->parseRequest(...), $data);
        }

        return $this->parseRequest($data);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function parseRequest(array $data): JsonRpcRequest
    {
        $jsonrpc = $data['jsonrpc'] ?? null;

        if ($jsonrpc !== '2.0') {
            throw JsonRpcException::invalidRequest('jsonrpc must be "2.0"');
        }

        $method = $data['method'] ?? null;

        if (!is_string($method) || $method === '') {
            throw JsonRpcException::invalidRequest('method must be a non-empty string');
        }

        $params = $data['params'] ?? [];

        if (!is_array($params)) {
            throw JsonRpcException::invalidRequest('params must be an object or array');
        }

        $id = $data['id'] ?? null;

        if ($id !== null && !is_string($id) && !is_int($id)) {
            throw JsonRpcException::invalidRequest('id must be a string, integer, or null');
        }

        return new JsonRpcRequest(
            method: $method,
            params: $params,
            id: $id,
        );
    }

    /**
     * Encode a response to JSON.
     */
    public function encode(JsonRpcResponse $response): string
    {
        return (string) json_encode($response->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Encode a batch of responses to JSON.
     *
     * @param list<JsonRpcResponse> $responses
     */
    public function encodeBatch(array $responses): string
    {
        $encoded = array_map(
            static fn(JsonRpcResponse $r): array => $r->toArray(),
            $responses,
        );

        return (string) json_encode($encoded, JSON_THROW_ON_ERROR);
    }
}
