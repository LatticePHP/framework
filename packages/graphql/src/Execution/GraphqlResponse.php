<?php

declare(strict_types=1);

namespace Lattice\GraphQL\Execution;

final readonly class GraphqlResponse
{
    /**
     * @param array<string, mixed>|null $data
     * @param array<array<string, mixed>> $errors
     * @param array<string, mixed> $extensions
     */
    public function __construct(
        public ?array $data = null,
        public array $errors = [],
        public array $extensions = [],
    ) {}

    /**
     * Create a successful response.
     *
     * @param array<string, mixed> $data
     */
    public static function success(array $data): self
    {
        return new self(data: $data);
    }

    /**
     * Create an error response.
     *
     * @param array<array<string, mixed>> $errors
     */
    public static function error(array $errors): self
    {
        return new self(data: null, errors: $errors);
    }

    /**
     * Convert to the standard GraphQL JSON response format.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];

        if ($this->data !== null) {
            $result['data'] = $this->data;
        }

        if (!empty($this->errors)) {
            $result['errors'] = $this->errors;
        }

        if (!empty($this->extensions)) {
            $result['extensions'] = $this->extensions;
        }

        // Per GraphQL spec, data key should be present even as null when errors exist
        if (empty($result)) {
            $result['data'] = null;
        }

        return $result;
    }

    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }
}
