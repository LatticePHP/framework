<?php

declare(strict_types=1);

namespace Lattice\Queue\Tests\Fixtures;

use Lattice\Queue\AbstractJob;

final class CounterJob extends AbstractJob
{
    /** @var int */
    public static int $counter = 0;

    /** @var string[] */
    public static array $log = [];

    public function __construct(
        public readonly string $label = 'default',
    ) {
    }

    public function handle(): void
    {
        self::$counter++;
        self::$log[] = $this->label;
    }

    public static function reset(): void
    {
        self::$counter = 0;
        self::$log = [];
    }
}
