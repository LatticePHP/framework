<?php

declare(strict_types=1);

namespace Lattice\JsonApi;

final class JsonApiErrorSerializer
{
    /**
     * Convert a throwable to a JSON:API error document.
     */
    public function fromException(\Throwable $exception, int $status = 500): JsonApiDocument
    {
        return JsonApiDocument::fromErrors([
            $this->buildError(
                status: (string) $status,
                title: $this->resolveTitle($status),
                detail: $exception->getMessage(),
            ),
        ]);
    }

    /**
     * Create an error document from a list of validation errors.
     *
     * @param array<string, string[]> $errors Field => messages
     */
    public function fromValidationErrors(array $errors, int $status = 422): JsonApiDocument
    {
        $jsonApiErrors = [];

        foreach ($errors as $field => $messages) {
            foreach ($messages as $message) {
                $jsonApiErrors[] = $this->buildError(
                    status: (string) $status,
                    title: 'Validation Error',
                    detail: $message,
                    source: ['pointer' => "/data/attributes/{$field}"],
                );
            }
        }

        return JsonApiDocument::fromErrors($jsonApiErrors);
    }

    /**
     * Create a single error document.
     */
    public function error(string $status, string $title, ?string $detail = null): JsonApiDocument
    {
        return JsonApiDocument::fromErrors([
            $this->buildError(status: $status, title: $title, detail: $detail),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildError(
        string $status,
        string $title,
        ?string $detail = null,
        ?array $source = null,
    ): array {
        $error = [
            'status' => $status,
            'title' => $title,
        ];

        if ($detail !== null) {
            $error['detail'] = $detail;
        }

        if ($source !== null) {
            $error['source'] = $source;
        }

        return $error;
    }

    private function resolveTitle(int $status): string
    {
        return match ($status) {
            400 => 'Bad Request',
            401 => 'Unauthorized',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            409 => 'Conflict',
            422 => 'Unprocessable Entity',
            429 => 'Too Many Requests',
            500 => 'Internal Server Error',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            default => 'Error',
        };
    }
}
