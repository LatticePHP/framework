<?php

declare(strict_types=1);

namespace Lattice\Nightwatch;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Nightwatch\Config\NightwatchConfig;
use Lattice\Nightwatch\Recorders\ExceptionRecorder;
use Lattice\Nightwatch\Recorders\RequestRecorder;
use Lattice\Nightwatch\Storage\NdjsonReader;
use Lattice\Nightwatch\Storage\NdjsonWriter;
use Lattice\Nightwatch\Storage\RetentionManager;
use Lattice\Nightwatch\Storage\StorageManager;
use Lattice\Nightwatch\Storage\TimePartitioner;
use Lattice\Nightwatch\Watchers\CacheWatcher;
use Lattice\Nightwatch\Watchers\EventWatcher;
use Lattice\Nightwatch\Watchers\ExceptionWatcher;
use Lattice\Nightwatch\Watchers\JobWatcher;
use Lattice\Nightwatch\Watchers\LogWatcher;
use Lattice\Nightwatch\Watchers\QueryWatcher;
use Lattice\Nightwatch\Watchers\RequestWatcher;

final class NightwatchServiceProvider
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(NightwatchConfig::class, NightwatchConfig::class);

        $container->singleton(TimePartitioner::class, function () use ($container): TimePartitioner {
            /** @var NightwatchConfig $config */
            $config = $container->get(NightwatchConfig::class);

            return new TimePartitioner($config->storagePath);
        });

        $container->singleton(NdjsonWriter::class, NdjsonWriter::class);
        $container->singleton(NdjsonReader::class, NdjsonReader::class);

        $container->singleton(StorageManager::class, function () use ($container): StorageManager {
            /** @var NightwatchConfig $config */
            $config = $container->get(NightwatchConfig::class);

            return new StorageManager(
                basePath: $config->storagePath,
                writer: $container->get(NdjsonWriter::class),
                reader: $container->get(NdjsonReader::class),
                partitioner: $container->get(TimePartitioner::class),
            );
        });

        $container->singleton(RetentionManager::class, function () use ($container): RetentionManager {
            /** @var NightwatchConfig $config */
            $config = $container->get(NightwatchConfig::class);

            return new RetentionManager(
                config: $config,
                partitioner: $container->get(TimePartitioner::class),
            );
        });

        // Dev-mode watchers
        $container->bind(RequestWatcher::class, function () use ($container): RequestWatcher {
            /** @var NightwatchConfig $config */
            $config = $container->get(NightwatchConfig::class);

            return new RequestWatcher(
                storage: $container->get(StorageManager::class),
                ignoredPaths: $config->ignoredPaths,
            );
        });

        $container->bind(QueryWatcher::class, function () use ($container): QueryWatcher {
            /** @var NightwatchConfig $config */
            $config = $container->get(NightwatchConfig::class);

            return new QueryWatcher(
                storage: $container->get(StorageManager::class),
                slowThresholdMs: $config->slowQueryThresholdMs,
            );
        });

        $container->bind(ExceptionWatcher::class, function () use ($container): ExceptionWatcher {
            /** @var NightwatchConfig $config */
            $config = $container->get(NightwatchConfig::class);

            return new ExceptionWatcher(
                storage: $container->get(StorageManager::class),
                ignoredExceptions: $config->ignoredExceptions,
            );
        });

        $container->bind(EventWatcher::class, function () use ($container): EventWatcher {
            return new EventWatcher(
                storage: $container->get(StorageManager::class),
            );
        });

        $container->bind(CacheWatcher::class, function () use ($container): CacheWatcher {
            return new CacheWatcher(
                storage: $container->get(StorageManager::class),
            );
        });

        $container->bind(JobWatcher::class, function () use ($container): JobWatcher {
            return new JobWatcher(
                storage: $container->get(StorageManager::class),
            );
        });

        $container->bind(LogWatcher::class, function () use ($container): LogWatcher {
            return new LogWatcher(
                storage: $container->get(StorageManager::class),
            );
        });

        // Prod-mode recorders
        $container->bind(RequestRecorder::class, function () use ($container): RequestRecorder {
            /** @var NightwatchConfig $config */
            $config = $container->get(NightwatchConfig::class);

            return new RequestRecorder(
                samplingRate: $config->samplingRate,
            );
        });

        $container->bind(ExceptionRecorder::class, function () use ($container): ExceptionRecorder {
            /** @var NightwatchConfig $config */
            $config = $container->get(NightwatchConfig::class);

            return new ExceptionRecorder(
                samplingRate: $config->samplingRate,
            );
        });
    }
}
