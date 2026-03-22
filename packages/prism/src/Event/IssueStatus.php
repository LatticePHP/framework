<?php

declare(strict_types=1);

namespace Lattice\Prism\Event;

enum IssueStatus: string
{
    case Unresolved = 'unresolved';
    case Resolved = 'resolved';
    case Ignored = 'ignored';
}
