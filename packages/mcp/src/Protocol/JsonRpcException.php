<?php

declare(strict_types=1);

namespace Lattice\Mcp\Protocol;

final class JsonRpcException extends \RuntimeException
{
    public const int PARSE_ERROR = -32700;
    public const int INVALID_REQUEST = -32600;
    public const int METHOD_NOT_FOUND = -32601;
    public const int INVALID_PARAMS = -32602;
    public const int INTERNAL_ERROR = -32603;

    private function __construct(
        string $message,
        int $code,
        public readonly string|int|null $requestId = null,
    ) {
        parent::__construct($message, $code);
    }

    public static function parseError(string $message, string|int|null $id = null): self
    {
        return new self('Parse error: ' . $message, self::PARSE_ERROR, $id);
    }

    public static function invalidRequest(string $message, string|int|null $id = null): self
    {
        return new self('Invalid Request: ' . $message, self::INVALID_REQUEST, $id);
    }

    public static function methodNotFound(string $method, string|int|null $id = null): self
    {
        return new self('Method not found: ' . $method, self::METHOD_NOT_FOUND, $id);
    }

    public static function invalidParams(string $message, string|int|null $id = null): self
    {
        return new self('Invalid params: ' . $message, self::INVALID_PARAMS, $id);
    }

    public static function internalError(string $message, string|int|null $id = null): self
    {
        return new self('Internal error: ' . $message, self::INTERNAL_ERROR, $id);
    }

    public function toResponse(): JsonRpcResponse
    {
        return JsonRpcResponse::error($this->requestId, $this->getCode(), $this->getMessage());
    }
}
