<?php

declare(strict_types=1);

namespace Lattice\Database;

/**
 * Discovers migration files across all registered modules.
 *
 * Convention: migrations live in database/migrations/ relative to the module class file,
 * or one directory level up for app-level modules.
 */
final class ModuleMigrationDiscoverer
{
    /**
     * Find all migration files across all modules.
     * Returns array of file paths, ordered by module dependency then filename.
     *
     * @param list<string> $moduleClasses Fully-qualified module class names in boot order
     * @return list<string> Absolute file paths to migration files
     */
    public function discover(array $moduleClasses): array
    {
        $migrations = [];

        foreach ($moduleClasses as $moduleClass) {
            if (!class_exists($moduleClass)) {
                continue;
            }

            $ref = new \ReflectionClass($moduleClass);
            $fileName = $ref->getFileName();

            if ($fileName === false) {
                continue;
            }

            $moduleDir = dirname($fileName);
            $migrationsDir = $moduleDir . '/database/migrations';

            // Also check one level up (for app-level modules)
            if (!is_dir($migrationsDir)) {
                $migrationsDir = dirname($moduleDir) . '/database/migrations';
            }

            if (is_dir($migrationsDir)) {
                $files = glob($migrationsDir . '/*.php');

                if ($files === false) {
                    continue;
                }

                sort($files); // Alphabetical within module
                foreach ($files as $file) {
                    $migrations[] = $file;
                }
            }
        }

        return $migrations;
    }

    /**
     * Find seeder classes across all modules.
     *
     * Convention: seeders live in database/seeders/ relative to the module class file,
     * or one directory level up for app-level modules.
     *
     * @param list<string> $moduleClasses Fully-qualified module class names in boot order
     * @return list<string> Absolute file paths to seeder files
     */
    public function discoverSeeders(array $moduleClasses): array
    {
        $seeders = [];

        foreach ($moduleClasses as $moduleClass) {
            if (!class_exists($moduleClass)) {
                continue;
            }

            $ref = new \ReflectionClass($moduleClass);
            $fileName = $ref->getFileName();

            if ($fileName === false) {
                continue;
            }

            $moduleDir = dirname($fileName);
            $seedersDir = $moduleDir . '/database/seeders';

            if (!is_dir($seedersDir)) {
                $seedersDir = dirname($moduleDir) . '/database/seeders';
            }

            if (is_dir($seedersDir)) {
                $files = glob($seedersDir . '/*.php');

                if ($files === false) {
                    continue;
                }

                sort($files);
                foreach ($files as $file) {
                    $seeders[] = $file;
                }
            }
        }

        return $seeders;
    }
}
