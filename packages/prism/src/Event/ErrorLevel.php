<?php

declare(strict_types=1);

namespace Lattice\Prism\Event;

enum ErrorLevel: string
{
    case Error = 'error';
    case Warning = 'warning';
    case Fatal = 'fatal';
    case Info = 'info';

    public static function isValid(string $value): bool
    {
        return self::tryFrom($value) !== null;
    }
}
