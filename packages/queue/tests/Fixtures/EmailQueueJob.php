<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Fixtures;

use Lattice\Queue\AbstractJob;

final class EmailQueueJob extends AbstractJob
{
    protected string $queue = 'emails';

    public static bool $handled = false;

    public function handle(): void
    {
        self::$handled = true;
    }

    public static function reset(): void
    {
        self::$handled = false;
    }
}
