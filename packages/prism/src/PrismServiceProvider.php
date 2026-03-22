<?php

declare(strict_types=1);

namespace Lattice\Prism;

use Lattice\Contracts\Container\ContainerInterface;
use Lattice\Prism\Auth\ApiKeyAuthenticator;
use Lattice\Prism\Database\IssueRepository;
use Lattice\Prism\Fingerprint\Fingerprinter;
use Lattice\Prism\Fingerprint\StacktraceNormalizer;
use Lattice\Prism\Storage\LocalFilesystemStorage;
use Lattice\Prism\Storage\NdjsonEventWriter;
use Lattice\Prism\Storage\StorageInterface;

final class PrismServiceProvider
{
    private const DEFAULT_STORAGE_PATH = 'storage/prism';

    public function register(ContainerInterface $container): void
    {
        $container->singleton(NdjsonEventWriter::class, NdjsonEventWriter::class);

        $container->singleton(StacktraceNormalizer::class, StacktraceNormalizer::class);

        $container->singleton(Fingerprinter::class, function () use ($container): Fingerprinter {
            return new Fingerprinter(
                normalizer: $container->get(StacktraceNormalizer::class),
            );
        });

        $container->singleton(StorageInterface::class, function (): StorageInterface {
            $basePath = getcwd() . '/' . self::DEFAULT_STORAGE_PATH;

            return new LocalFilesystemStorage($basePath);
        });

        $container->singleton(IssueRepository::class, IssueRepository::class);

        $container->singleton(ApiKeyAuthenticator::class, ApiKeyAuthenticator::class);

        $container->singleton(PrismAdminGuard::class, function (): PrismAdminGuard {
            return new PrismAdminGuard();
        });
    }
}
