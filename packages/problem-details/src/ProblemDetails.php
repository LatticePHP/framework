<?php

declare(strict_types=1);

namespace Lattice\ProblemDetails;

final class ProblemDetails implements \JsonSerializable
{
    public function __construct(
        public readonly int $status,
        public readonly string $title,
        public readonly ?string $type = null,
        public readonly ?string $detail = null,
        public readonly ?string $instance = null,
        public readonly ?string $correlationId = null,
        public readonly array $extensions = [],
    ) {}

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    public function toArray(): array
    {
        $data = [];

        if ($this->type !== null) {
            $data['type'] = $this->type;
        }

        $data['title'] = $this->title;
        $data['status'] = $this->status;

        if ($this->detail !== null) {
            $data['detail'] = $this->detail;
        }

        if ($this->instance !== null) {
            $data['instance'] = $this->instance;
        }

        if ($this->correlationId !== null) {
            $data['correlationId'] = $this->correlationId;
        }

        foreach ($this->extensions as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    public static function badRequest(string $detail, array $fieldErrors = []): self
    {
        return new self(
            status: 400,
            title: 'Bad Request',
            detail: $detail,
            extensions: $fieldErrors !== [] ? ['field_errors' => $fieldErrors] : [],
        );
    }

    public static function unauthorized(string $detail = 'Unauthorized'): self
    {
        return new self(
            status: 401,
            title: 'Unauthorized',
            detail: $detail,
        );
    }

    public static function forbidden(string $detail = 'Forbidden'): self
    {
        return new self(
            status: 403,
            title: 'Forbidden',
            detail: $detail,
        );
    }

    public static function notFound(string $detail = 'Not Found'): self
    {
        return new self(
            status: 404,
            title: 'Not Found',
            detail: $detail,
        );
    }

    public static function conflict(string $detail): self
    {
        return new self(
            status: 409,
            title: 'Conflict',
            detail: $detail,
        );
    }

    public static function unprocessableEntity(string $detail, array $fieldErrors = []): self
    {
        return new self(
            status: 422,
            title: 'Unprocessable Entity',
            detail: $detail,
            extensions: $fieldErrors !== [] ? ['field_errors' => $fieldErrors] : [],
        );
    }

    public static function internalError(string $detail = 'Internal Server Error'): self
    {
        return new self(
            status: 500,
            title: 'Internal Server Error',
            detail: $detail,
        );
    }

    public static function tooManyRequests(string $detail = 'Too Many Requests', ?int $retryAfter = null): self
    {
        return new self(
            status: 429,
            title: 'Too Many Requests',
            detail: $detail,
            extensions: $retryAfter !== null ? ['retryAfter' => $retryAfter] : [],
        );
    }
}
