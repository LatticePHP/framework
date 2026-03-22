<?php

declare(strict_types=1);

namespace Lattice\Prism\Database;

use Lattice\Prism\Event\ErrorEvent;
use Lattice\Prism\Event\ErrorLevel;
use Lattice\Prism\Event\IssueStatus;

final class IssueRepository
{
    /**
     * In-memory store keyed by issue ID.
     *
     * @var array<string, Issue>
     */
    private array $issues = [];

    /**
     * Fingerprint index: project_id:fingerprint -> issue ID.
     *
     * @var array<string, string>
     */
    private array $fingerprintIndex = [];

    /**
     * Next auto-increment ID counter.
     */
    private int $nextId = 1;

    /**
     * Find an existing issue by fingerprint or create a new one.
     * Returns [Issue, bool isNew, bool isRegression].
     *
     * @return array{issue: Issue, is_new: bool, is_regression: bool}
     */
    public function findOrCreateByFingerprint(
        string $projectId,
        string $fingerprint,
        ErrorEvent $event,
    ): array {
        $key = $projectId . ':' . $fingerprint;

        if (isset($this->fingerprintIndex[$key])) {
            $issueId = $this->fingerprintIndex[$key];
            $existing = $this->issues[$issueId];

            $isRegression = $existing->status === IssueStatus::Resolved;

            // Update: increment count, update last_seen, reopen if resolved
            $updated = new Issue(
                id: $existing->id,
                projectId: $existing->projectId,
                fingerprint: $existing->fingerprint,
                title: $existing->title,
                level: $existing->level,
                status: $isRegression ? IssueStatus::Unresolved : $existing->status,
                count: $existing->count + 1,
                firstSeen: $existing->firstSeen,
                lastSeen: $event->timestamp->format('c'),
                culprit: $existing->culprit,
                platform: $existing->platform,
                environment: $existing->environment,
                release: $event->release ?? $existing->release,
                createdAt: $existing->createdAt,
                updatedAt: date('c'),
            );

            $this->issues[$issueId] = $updated;

            return [
                'issue' => $updated,
                'is_new' => false,
                'is_regression' => $isRegression,
            ];
        }

        // Build title from exception info
        $title = $this->buildTitle($event);
        $culprit = $this->buildCulprit($event);

        $id = (string) $this->nextId++;
        $now = date('c');

        $issue = new Issue(
            id: $id,
            projectId: $projectId,
            fingerprint: $fingerprint,
            title: $title,
            level: $event->level,
            status: IssueStatus::Unresolved,
            count: 1,
            firstSeen: $event->timestamp->format('c'),
            lastSeen: $event->timestamp->format('c'),
            culprit: $culprit,
            platform: $event->platform,
            environment: $event->environment,
            release: $event->release,
            createdAt: $now,
            updatedAt: $now,
        );

        $this->issues[$id] = $issue;
        $this->fingerprintIndex[$key] = $id;

        return [
            'issue' => $issue,
            'is_new' => true,
            'is_regression' => false,
        ];
    }

    public function findById(string $id): ?Issue
    {
        return $this->issues[$id] ?? null;
    }

    /**
     * List issues for a project with optional filters.
     *
     * @param array<string, mixed> $filters Supported keys: status, level, environment, platform, search
     * @return list<Issue>
     */
    public function listByProject(
        string $projectId,
        array $filters = [],
        string $sortBy = 'last_seen',
        string $sortDir = 'desc',
        int $limit = 25,
        int $offset = 0,
    ): array {
        $results = [];

        foreach ($this->issues as $issue) {
            if ($issue->projectId !== $projectId) {
                continue;
            }

            if (isset($filters['status']) && $issue->status->value !== $filters['status']) {
                continue;
            }

            if (isset($filters['level']) && $issue->level->value !== $filters['level']) {
                continue;
            }

            if (isset($filters['environment']) && $issue->environment !== $filters['environment']) {
                continue;
            }

            if (isset($filters['platform']) && $issue->platform !== $filters['platform']) {
                continue;
            }

            if (isset($filters['search']) && is_string($filters['search']) && $filters['search'] !== '') {
                $needle = strtolower($filters['search']);
                if (!str_contains(strtolower($issue->title), $needle)) {
                    continue;
                }
            }

            $results[] = $issue;
        }

        // Sort
        usort($results, function (Issue $a, Issue $b) use ($sortBy, $sortDir): int {
            $cmp = match ($sortBy) {
                'first_seen' => strcmp($a->firstSeen, $b->firstSeen),
                'count' => $a->count <=> $b->count,
                default => strcmp($a->lastSeen, $b->lastSeen), // last_seen
            };

            return $sortDir === 'asc' ? $cmp : -$cmp;
        });

        return array_slice($results, $offset, $limit);
    }

    /**
     * Count issues matching filters.
     *
     * @param array<string, mixed> $filters
     */
    public function countByProject(string $projectId, array $filters = []): int
    {
        return count($this->listByProject($projectId, $filters, limit: PHP_INT_MAX));
    }

    /**
     * Update issue status.
     */
    public function updateStatus(string $issueId, IssueStatus $status): ?Issue
    {
        if (!isset($this->issues[$issueId])) {
            return null;
        }

        $existing = $this->issues[$issueId];

        $updated = new Issue(
            id: $existing->id,
            projectId: $existing->projectId,
            fingerprint: $existing->fingerprint,
            title: $existing->title,
            level: $existing->level,
            status: $status,
            count: $existing->count,
            firstSeen: $existing->firstSeen,
            lastSeen: $existing->lastSeen,
            culprit: $existing->culprit,
            platform: $existing->platform,
            environment: $existing->environment,
            release: $existing->release,
            createdAt: $existing->createdAt,
            updatedAt: date('c'),
        );

        $this->issues[$issueId] = $updated;

        return $updated;
    }

    /**
     * Get all issues (for stats).
     *
     * @return list<Issue>
     */
    public function all(): array
    {
        return array_values($this->issues);
    }

    /**
     * Reset the repository (for testing).
     */
    public function reset(): void
    {
        $this->issues = [];
        $this->fingerprintIndex = [];
        $this->nextId = 1;
    }

    private function buildTitle(ErrorEvent $event): string
    {
        $parts = [];

        if ($event->exceptionType !== null) {
            $parts[] = $event->exceptionType;
        }

        if ($event->exceptionMessage !== null) {
            $parts[] = $event->exceptionMessage;
        }

        if ($parts === []) {
            return sprintf('[%s] %s', $event->level->value, $event->transaction ?? 'Unknown');
        }

        return implode(': ', $parts);
    }

    private function buildCulprit(ErrorEvent $event): ?string
    {
        if ($event->stacktrace === []) {
            return null;
        }

        $topFrame = $event->stacktrace[0];

        return $topFrame->file . ':' . $topFrame->line;
    }
}
