<?php

declare(strict_types=1);

namespace Lattice\Core\Bootstrap;

/**
 * Ensures the application's storage directory structure exists.
 */
final class StorageDirectories
{
    /**
     * Ensure all required storage directories exist.
     */
    public static function ensure(string $basePath): void
    {
        $dirs = [
            $basePath . '/storage',
            $basePath . '/storage/app',
            $basePath . '/storage/framework',
            $basePath . '/storage/framework/cache',
            $basePath . '/storage/framework/views',
            $basePath . '/storage/logs',
        ];

        foreach ($dirs as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
    }
}
