<?php

declare(strict_types=1);

namespace Lattice\DevTools\Console\Command;

use Lattice\DevTools\Console\Command as BaseCommand;
use Lattice\DevTools\Console\Input;
use Lattice\DevTools\Console\Output;

final class CacheManifestCommand extends BaseCommand
{
    /** @var callable|null */
    private $compileCallback;

    public function __construct(?callable $compileCallback = null)
    {
        $this->compileCallback = $compileCallback;
    }

    public function name(): string
    {
        return 'manifest:cache';
    }

    public function description(): string
    {
        return 'Compile and cache manifests for production';
    }

    public function handle(Input $input, Output $output): int
    {
        $output->info('Compiling manifests...');

        if ($this->compileCallback !== null) {
            ($this->compileCallback)();
        }

        $output->success('Manifests compiled successfully.');

        return 0;
    }
}
