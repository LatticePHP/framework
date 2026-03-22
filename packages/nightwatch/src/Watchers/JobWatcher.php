<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Watchers;

use Lattice\Nightwatch\Entry;
use Lattice\Nightwatch\EntryType;
use Lattice\Nightwatch\Storage\StorageManager;

final class JobWatcher extends AbstractWatcher
{
    /**
     * Capture a queued job event.
     *
     * @param array<string, mixed> $jobData
     */
    public function capture(array $jobData, ?string $batchId = null): Entry
    {
        $entry = new Entry(
            type: EntryType::Job,
            data: [
                'job_class' => $jobData['job_class'] ?? 'Unknown',
                'queue' => $jobData['queue'] ?? 'default',
                'connection' => $jobData['connection'] ?? 'default',
                'payload' => $this->truncatePayload($jobData['payload'] ?? []),
                'status' => $jobData['status'] ?? 'queued',
                'duration_ms' => $jobData['duration_ms'] ?? null,
                'attempt' => $jobData['attempt'] ?? 1,
                'max_tries' => $jobData['max_tries'] ?? null,
                'exception' => $jobData['exception'] ?? null,
            ],
            tags: $this->buildTags($jobData),
            batchId: $batchId,
        );

        $this->handle($entry);

        return $entry;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function truncatePayload(array $payload): array
    {
        $encoded = json_encode($payload);

        if ($encoded === false) {
            return ['_error' => 'Failed to serialize payload'];
        }

        // Limit payload to 10KB
        if (strlen($encoded) > 10240) {
            return ['_truncated' => true, '_original_size' => strlen($encoded)];
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $jobData
     * @return list<string>
     */
    private function buildTags(array $jobData): array
    {
        $tags = [];
        $status = $jobData['status'] ?? 'queued';
        $tags[] = 'status:' . $status;
        $tags[] = 'queue:' . ($jobData['queue'] ?? 'default');

        if ($status === 'failed') {
            $tags[] = 'failed';
        }

        return $tags;
    }
}
