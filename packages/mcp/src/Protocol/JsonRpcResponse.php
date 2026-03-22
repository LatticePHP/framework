<?php

declare(strict_types=1);

namespace Lattice\Mcp\Protocol;

final class JsonRpcResponse
{
    /**
     * @param array<string, mixed>|null $error
     */
    private function __construct(
        public readonly string|int|null $id,
        public readonly mixed $result = null,
        public readonly ?array $error = null,
    ) {}

    public static function success(string|int|null $id, mixed $result): self
    {
        return new self(id: $id, result: $result);
    }

    /**
     * @param array<string, mixed>|null $data
     */
    public static function error(string|int|null $id, int $code, string $message, ?array $data = null): self
    {
        $error = ['code' => $code, 'message' => $message];

        if ($data !== null) {
            $error['data'] = $data;
        }

        return new self(id: $id, error: $error);
    }

    public function isError(): bool
    {
        return $this->error !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $response = [
            'jsonrpc' => '2.0',
            'id' => $this->id,
        ];

        if ($this->error !== null) {
            $response['error'] = $this->error;
        } else {
            $response['result'] = $this->result;
        }

        return $response;
    }
}
