<?php

declare(strict_types=1);

namespace Lattice\Ai\Providers;

use Lattice\Ai\Config\AiConfig;
use Lattice\Ai\Exceptions\AiException;
use Lattice\Ai\Exceptions\AuthenticationException;
use Lattice\Ai\Exceptions\ContextLengthExceededException;
use Lattice\Ai\Exceptions\RateLimitException;
use Lattice\HttpClient\HttpClient;
use Lattice\HttpClient\HttpClientResponse;

abstract class AbstractProvider implements ProviderInterface
{
    protected HttpClient $httpClient;
    protected string $apiKey;
    protected string $baseUrl;
    protected string $defaultModel;
    protected int $timeout;
    protected int $maxRetries;
    protected float $baseDelay;
    protected float $maxDelay;

    /** @var list<int> HTTP status codes that should be retried */
    private const array RETRYABLE_STATUS_CODES = [429, 500, 502, 503, 529];

    /** @var list<int> HTTP status codes that should NOT be retried */
    private const array NON_RETRYABLE_STATUS_CODES = [400, 401, 403];

    public function __construct(
        protected readonly AiConfig $config,
        ?HttpClient $httpClient = null,
    ) {
        $providerName = $this->name();
        $this->apiKey = $this->config->apiKey($providerName);
        $this->baseUrl = $this->config->baseUrl($providerName);
        $this->defaultModel = $this->config->defaultModel($providerName);
        $this->timeout = $this->config->timeout($providerName);

        $providerConfig = $this->config->providerConfig($providerName);
        $this->maxRetries = (int) ($providerConfig['max_retries'] ?? 3);
        $this->baseDelay = (float) ($providerConfig['retry_base_delay'] ?? 1.0);
        $this->maxDelay = (float) ($providerConfig['retry_max_delay'] ?? 30.0);

        if ($httpClient !== null) {
            // Use the provided client as-is (e.g. FakeHttpClient for testing)
            $this->httpClient = $httpClient;
        } else {
            $this->httpClient = (new HttpClient())
                ->withTimeout($this->timeout)
                ->withHeaders($this->defaultHeaders());
        }
    }

    /**
     * Get default HTTP headers for the provider.
     *
     * @return array<string, string>
     */
    abstract protected function defaultHeaders(): array;

    /**
     * Resolve the model to use.
     *
     * @param array<string, mixed> $options
     */
    protected function resolveModel(array $options): string
    {
        return (string) ($options['model'] ?? $this->defaultModel);
    }

    /**
     * Send an HTTP request with retry logic.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $options
     */
    protected function sendRequest(string $method, string $url, ?array $body = null, array $options = []): HttpClientResponse
    {
        return $this->retryWithBackoff(function () use ($method, $url, $body, $options): HttpClientResponse {
            $fullUrl = $this->baseUrl . $url;

            $response = match ($method) {
                'POST' => $this->httpClient->post($fullUrl, $body, $options),
                'GET' => $this->httpClient->get($fullUrl, $options),
                'PUT' => $this->httpClient->put($fullUrl, $body, $options),
                'DELETE' => $this->httpClient->delete($fullUrl, $options),
                default => throw new AiException("Unsupported HTTP method: {$method}"),
            };

            if ($response->failed()) {
                $this->handleErrorResponse($response);
            }

            return $response;
        });
    }

    /**
     * Retry a callable with exponential backoff and jitter.
     *
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    protected function retryWithBackoff(callable $callback): mixed
    {
        $attempt = 0;
        $lastException = null;

        while ($attempt <= $this->maxRetries) {
            try {
                return $callback();
            } catch (RateLimitException $e) {
                $lastException = $e;

                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }

                // Use Retry-After if available
                if ($e->retryAfterSeconds !== null) {
                    $this->sleep($e->retryAfterSeconds);
                } else {
                    $this->sleepWithBackoff($attempt);
                }
            } catch (AiException $e) {
                // Do not retry client errors
                if (in_array($e->getCode(), self::NON_RETRYABLE_STATUS_CODES, true)) {
                    throw $e;
                }

                // Only retry on known transient codes
                if (!in_array($e->getCode(), self::RETRYABLE_STATUS_CODES, true)) {
                    throw $e;
                }

                $lastException = $e;

                if ($attempt >= $this->maxRetries) {
                    throw $e;
                }

                $this->sleepWithBackoff($attempt);
            }

            $attempt++;
        }

        throw $lastException ?? new AiException('Request failed after retries.');
    }

    /**
     * Calculate the backoff delay with jitter and sleep.
     */
    protected function sleepWithBackoff(int $attempt): void
    {
        $delay = min($this->baseDelay * (2 ** $attempt), $this->maxDelay);
        // Add jitter (0-50% of delay)
        $jitter = $delay * 0.5 * (mt_rand() / mt_getrandmax());
        $this->sleep($delay + $jitter);
    }

    /**
     * Sleep for the given number of seconds (overridable for testing).
     */
    protected function sleep(float $seconds): void
    {
        usleep((int) ($seconds * 1_000_000));
    }

    /**
     * Handle an error HTTP response and throw an appropriate exception.
     */
    protected function handleErrorResponse(HttpClientResponse $response): never
    {
        $status = $response->status();
        $body = $response->body();

        $message = $this->extractErrorMessage($body) ?? "API request failed with status {$status}";

        match (true) {
            $status === 401 || $status === 403 => throw new AuthenticationException($message),
            $status === 429 => throw new RateLimitException(
                $message,
                $this->extractRetryAfter($response),
            ),
            str_contains(strtolower($message), 'context length') ||
            str_contains(strtolower($message), 'max tokens') ||
            str_contains(strtolower($message), 'too many tokens') => throw new ContextLengthExceededException($message),
            default => throw new AiException($message, $status),
        };
    }

    /**
     * Extract a human-readable error message from the response body.
     */
    protected function extractErrorMessage(string $body): ?string
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return null;
        }

        // Common patterns: {"error": {"message": "..."}} or {"error": "..."} or {"message": "..."}
        if (isset($decoded['error'])) {
            if (is_array($decoded['error']) && isset($decoded['error']['message'])) {
                return (string) $decoded['error']['message'];
            }

            if (is_string($decoded['error'])) {
                return $decoded['error'];
            }
        }

        if (isset($decoded['message']) && is_string($decoded['message'])) {
            return $decoded['message'];
        }

        return null;
    }

    /**
     * Extract Retry-After header value.
     */
    protected function extractRetryAfter(HttpClientResponse $response): ?int
    {
        $headers = $response->headers();
        $retryAfter = $headers['Retry-After'] ?? $headers['retry-after'] ?? null;

        if ($retryAfter === null) {
            return null;
        }

        return (int) $retryAfter;
    }

    /**
     * Parse an SSE (Server-Sent Events) stream body into events.
     *
     * @return list<array{event: string, data: string}>
     */
    protected function parseSSE(string $body): array
    {
        $events = [];
        $lines = explode("\n", $body);
        $currentEvent = '';
        $currentData = '';

        foreach ($lines as $line) {
            $line = rtrim($line, "\r");

            if ($line === '') {
                // Empty line means end of event
                if ($currentData !== '') {
                    $events[] = [
                        'event' => $currentEvent ?: 'message',
                        'data' => trim($currentData),
                    ];
                    $currentEvent = '';
                    $currentData = '';
                }
                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $currentEvent = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data = substr($line, 5);
                if (str_starts_with($data, ' ')) {
                    $data = substr($data, 1);
                }
                $currentData .= ($currentData !== '' ? "\n" : '') . $data;
            }
            // Ignore id:, retry:, and comment lines
        }

        // Handle final event without trailing newline
        if ($currentData !== '') {
            $events[] = [
                'event' => $currentEvent ?: 'message',
                'data' => trim($currentData),
            ];
        }

        return $events;
    }

    public function supports(ProviderCapability $capability): bool
    {
        return in_array($capability, $this->capabilities(), true);
    }
}
