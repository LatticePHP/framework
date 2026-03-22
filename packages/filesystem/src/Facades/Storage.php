<?php

declare(strict_types=1);

namespace Lattice\Filesystem\Facades;

use Lattice\Filesystem\Driver\InMemoryFilesystem;
use Lattice\Filesystem\FilesystemInterface;
use Lattice\Filesystem\FilesystemManager;

final class Storage
{
    private static ?FilesystemManager $manager = null;

    /**
     * Set the FilesystemManager instance used by the facade.
     */
    public static function setManager(FilesystemManager $manager): void
    {
        self::$manager = $manager;
    }

    /**
     * Get the FilesystemManager instance, resolving from the container if not set.
     */
    public static function getManager(): FilesystemManager
    {
        if (self::$manager === null) {
            self::$manager = \app(FilesystemManager::class);
        }

        return self::$manager;
    }

    /**
     * Get a filesystem disk instance.
     */
    public static function disk(?string $name = null): FilesystemInterface
    {
        return self::getManager()->disk($name ?? 'default');
    }

    /**
     * Write contents to a file on the default disk.
     */
    public static function put(string $path, string $contents): bool
    {
        self::disk()->write($path, $contents);
        return true;
    }

    /**
     * Get the contents of a file from the default disk.
     */
    public static function get(string $path): string
    {
        return self::disk()->read($path);
    }

    /**
     * Delete a file from the default disk.
     */
    public static function delete(string $path): bool
    {
        return self::disk()->delete($path);
    }

    /**
     * Determine if a file exists on the default disk.
     */
    public static function exists(string $path): bool
    {
        return self::disk()->exists($path);
    }

    /**
     * Get the URL for a file on the default disk.
     */
    public static function url(string $path): string
    {
        return self::disk()->url($path);
    }

    /**
     * Replace the filesystem with an in-memory fake for testing.
     */
    public static function fake(string $disk = 'default'): InMemoryFilesystem
    {
        $fake = new InMemoryFilesystem();

        if (self::$manager === null) {
            self::$manager = new FilesystemManager();
        }

        self::$manager->addDisk($disk, $fake);

        return $fake;
    }

    /**
     * Reset the facade instance (useful in tests).
     */
    public static function reset(): void
    {
        self::$manager = null;
    }
}
