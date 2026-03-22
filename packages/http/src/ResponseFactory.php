<?php

declare(strict_types=1);

namespace Lattice\Http;

use Lattice\Database\Pagination\Paginator;

final class ResponseFactory
{
    /**
     * Create a JSON response.
     *
     * @param array<string, string> $headers
     */
    public static function json(mixed $data, int $status = 200, array $headers = []): Response
    {
        return new Response(
            statusCode: $status,
            body: $data,
            headers: array_merge(['Content-Type' => 'application/json'], $headers),
        );
    }

    /**
     * Create a 201 Created response.
     *
     * @param array<string, string> $headers
     */
    public static function created(mixed $data = null, array $headers = []): Response
    {
        return self::json($data, 201, $headers);
    }

    /**
     * Create a 202 Accepted response.
     */
    public static function accepted(mixed $data = null): Response
    {
        return self::json($data, 202);
    }

    /**
     * Create a 204 No Content response.
     */
    public static function noContent(): Response
    {
        return new Response(statusCode: 204, body: null, headers: []);
    }

    /**
     * Create a paginated response with meta/links.
     *
     * Supports Lattice\Database\Pagination\Paginator and Illuminate's LengthAwarePaginator.
     *
     * @param class-string<Resource>|null $resourceClass
     */
    public static function paginated(mixed $paginator, ?string $resourceClass = null): Response
    {
        // Handle Illuminate LengthAwarePaginator
        if (
            interface_exists(\Illuminate\Contracts\Pagination\LengthAwarePaginator::class)
            && $paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator
        ) {
            $items = $paginator->items();
            if ($resourceClass !== null && is_subclass_of($resourceClass, Resource::class)) {
                $data = $resourceClass::collection($items);
            } else {
                $data = array_values(
                    $items instanceof \Illuminate\Support\Collection ? $items->toArray() : (array) $items,
                );
            }

            return self::json([
                'data' => $data,
                'meta' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'links' => [
                    'first' => $paginator->url(1),
                    'last' => $paginator->url($paginator->lastPage()),
                    'prev' => $paginator->previousPageUrl(),
                    'next' => $paginator->nextPageUrl(),
                ],
            ]);
        }

        // Handle our lightweight Paginator
        if ($paginator instanceof Paginator) {
            $items = $paginator->items();
            if ($resourceClass !== null && is_subclass_of($resourceClass, Resource::class)) {
                $data = $resourceClass::collection($items);
            } else {
                $data = $items;
            }

            return self::json([
                'data' => $data,
                'meta' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'has_more' => $paginator->hasMorePages(),
                ],
            ]);
        }

        // Fallback: just return as-is
        return self::json(['data' => $paginator]);
    }

    /**
     * Create a Problem Details (RFC 9457) error response.
     *
     * @param array<string, mixed> $errors
     */
    public static function error(string $message, int $status = 500, array $errors = []): Response
    {
        $body = [
            'type' => "https://httpstatuses.io/{$status}",
            'title' => self::statusText($status),
            'status' => $status,
            'detail' => $message,
        ];

        if (!empty($errors)) {
            $body['errors'] = $errors;
        }

        return self::json($body, $status);
    }

    /**
     * Create a 422 validation error response with field errors.
     *
     * @param array<string, mixed> $errors
     */
    public static function validationError(array $errors): Response
    {
        return self::error('The given data was invalid.', 422, $errors);
    }

    /**
     * Create a response with rate limit headers applied.
     *
     * @param int $limit The maximum number of requests allowed
     * @param int $remaining The number of requests remaining
     * @param int $reset Unix timestamp when the rate limit resets
     * @param bool $exceeded Whether the rate limit has been exceeded
     */
    public static function withRateLimitHeaders(
        Response $response,
        int $limit,
        int $remaining,
        int $reset,
        bool $exceeded = false,
    ): Response {
        $result = $response
            ->withHeader('X-RateLimit-Limit', (string) $limit)
            ->withHeader('X-RateLimit-Remaining', (string) max(0, $remaining))
            ->withHeader('X-RateLimit-Reset', (string) $reset);

        if ($exceeded) {
            $retryAfter = max(0, $reset - time());
            $result = $result->withHeader('Retry-After', (string) $retryAfter);
        }

        return $result;
    }

    /**
     * Create a 429 Too Many Requests response with rate limit headers.
     */
    public static function tooManyRequests(int $limit, int $reset): Response
    {
        $response = self::error('Too many requests.', 429);

        return self::withRateLimitHeaders($response, $limit, 0, $reset, exceeded: true);
    }

    private static function statusText(int $code): string
    {
        return match ($code) {
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            204 => 'No Content',
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
