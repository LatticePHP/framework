<?php

declare(strict_types=1);

namespace Lattice\Nightwatch\Config;

final class NightwatchConfig
{
    public const DEFAULT_DEV_RETENTION_DAYS = 7;
    public const DEFAULT_PROD_RETENTION_DAYS = 90;
    public const DEFAULT_SLOW_QUERY_THRESHOLD_MS = 100;
    public const DEFAULT_SAMPLING_RATE = 1.0;

    /**
     * @param array<string, bool> $watchers Map of watcher class => enabled
     * @param array<string, bool> $recorders Map of recorder class => enabled
     * @param list<string> $ignoredPaths Paths to exclude from request watching
     * @param list<string> $ignoredExceptions Exception classes to exclude
     */
    public function __construct(
        public readonly string $storagePath = 'storage/nightwatch',
        public readonly string $mode = 'auto',
        public readonly int $devRetentionDays = self::DEFAULT_DEV_RETENTION_DAYS,
        public readonly int $prodRetentionDays = self::DEFAULT_PROD_RETENTION_DAYS,
        public readonly bool $enabled = true,
        public readonly array $watchers = [],
        public readonly array $recorders = [],
        public readonly float $samplingRate = self::DEFAULT_SAMPLING_RATE,
        public readonly int $slowQueryThresholdMs = self::DEFAULT_SLOW_QUERY_THRESHOLD_MS,
        public readonly array $ignoredPaths = [],
        public readonly array $ignoredExceptions = [],
    ) {}

    public function resolvedMode(?string $appEnv = null): string
    {
        if ($this->mode !== 'auto') {
            return $this->mode;
        }

        return match ($appEnv) {
            'production', 'staging' => 'prod',
            default => 'dev',
        };
    }

    public function isDevMode(?string $appEnv = null): bool
    {
        return $this->resolvedMode($appEnv) === 'dev';
    }

    public function isProdMode(?string $appEnv = null): bool
    {
        return $this->resolvedMode($appEnv) === 'prod';
    }

    public function retentionDays(?string $appEnv = null): int
    {
        return $this->isDevMode($appEnv) ? $this->devRetentionDays : $this->prodRetentionDays;
    }
}
