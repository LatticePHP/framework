<?php

declare(strict_types=1);

namespace Lattice\Filesystem\Illuminate;

use Illuminate\Filesystem\FilesystemManager as BaseManager;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Container\Container;

/**
 * Wraps Illuminate's FilesystemManager to provide full Flysystem functionality.
 *
 * This is the recommended production path. It gives you access to all Laravel
 * filesystem drivers (local, S3, SFTP, GCS, etc.) plus the full Flysystem API.
 *
 * The lightweight Lattice\Filesystem\FilesystemManager remains available as a
 * fallback for testing or simple use cases.
 */
final class IlluminateFilesystemManager
{
    private BaseManager $manager;

    public function __construct(array $config)
    {
        $container = new Container();
        $container['config'] = [
            'filesystems.default' => $config['default'] ?? 'local',
            'filesystems.disks' => $config['disks'] ?? [
                'local' => [
                    'driver' => 'local',
                    'root' => $config['root'] ?? sys_get_temp_dir(),
                ],
            ],
        ];

        $this->manager = new BaseManager($container);
    }

    public function disk(?string $name = null): FilesystemAdapter
    {
        return $this->manager->disk($name);
    }

    /**
     * Full Flysystem access: S3, SFTP, GCS, etc.
     */
    public function getManager(): BaseManager
    {
        return $this->manager;
    }
}
