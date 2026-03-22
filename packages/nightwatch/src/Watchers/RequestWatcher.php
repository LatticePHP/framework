<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Watchers;

use DateTimeImmutable;
use Lattice\Nightwatch\Entry;
use Lattice\Nightwatch\EntryType;
use Lattice\Nightwatch\Storage\StorageManager;

final class RequestWatcher extends AbstractWatcher
{
    private const REDACTED_HEADERS = [
        'authorization',
        'cookie',
        'set-cookie',
        'x-csrf-token',
        'x-xsrf-token',
    ];

    /** @var list<string> */
    private array $ignoredPaths;

    /**
     * @param list<string> $ignoredPaths
     * @param list<string> $redactedHeaders
     */
    public function __construct(
        StorageManager $storage,
        array $ignoredPaths = [],
        private readonly array $redactedHeaders = self::REDACTED_HEADERS,
    ) {
        parent::__construct($storage);
        $this->ignoredPaths = $ignoredPaths;
    }

    /**
     * Capture a request and create an entry.
     *
     * @param array<string, mixed> $requestData
     */
    public function capture(array $requestData, ?string $batchId = null): Entry
    {
        $headers = $requestData['headers'] ?? [];
        $redactedHeaderKeys = array_map('strtolower', $this->redactedHeaders);

        $sanitizedHeaders = [];
        foreach ($headers as $name => $value) {
            if (in_array(strtolower((string) $name), $redactedHeaderKeys, true)) {
                $sanitizedHeaders[$name] = '********';
            } else {
                $sanitizedHeaders[$name] = $value;
            }
        }

        $entry = new Entry(
            type: EntryType::Request,
            data: [
                'method' => $requestData['method'] ?? 'GET',
                'uri' => $requestData['uri'] ?? '/',
                'route_name' => $requestData['route_name'] ?? null,
                'controller' => $requestData['controller'] ?? null,
                'headers' => $sanitizedHeaders,
                'ip' => $requestData['ip'] ?? null,
                'session_id' => $requestData['session_id'] ?? null,
                'status' => $requestData['status'] ?? 200,
                'response_size' => $requestData['response_size'] ?? null,
                'content_type' => $requestData['content_type'] ?? null,
                'duration_ms' => $requestData['duration_ms'] ?? 0,
                'user_id' => $requestData['user_id'] ?? null,
                'middleware' => $requestData['middleware'] ?? [],
            ],
            tags: $this->buildTags($requestData),
            batchId: $batchId,
        );

        $this->handle($entry);

        return $entry;
    }

    public function shouldRecord(Entry $entry): bool
    {
        $uri = $entry->data['uri'] ?? '';

        foreach ($this->ignoredPaths as $pattern) {
            if (str_starts_with($uri, $pattern)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param array<string, mixed> $requestData
     * @return list<string>
     */
    private function buildTags(array $requestData): array
    {
        $tags = [];
        $method = $requestData['method'] ?? 'GET';
        $status = $requestData['status'] ?? 200;

        $tags[] = 'method:' . strtoupper($method);
        $tags[] = 'status:' . $status;

        if ($status >= 400) {
            $tags[] = 'error';
        }

        if ($status >= 500) {
            $tags[] = 'server_error';
        }

        return $tags;
    }
}
