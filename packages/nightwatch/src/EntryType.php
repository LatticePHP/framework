<?php

declare(strict_types=1);

namespace Lattice\Nightwatch;

enum EntryType: string
{
    case Request = 'request';
    case Query = 'query';
    case Exception = 'exception';
    case Event = 'event';
    case Cache = 'cache';
    case Job = 'job';
    case Mail = 'mail';
    case Log = 'log';
    case Model = 'model';
    case Gate = 'gate';
}
