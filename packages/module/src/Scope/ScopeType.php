<?php

declare(strict_types=1);

namespace Lattice\Module\Scope;

enum ScopeType: string
{
    case Singleton = 'singleton';
    case Request = 'request';
    case Worker = 'worker';
    case Transient = 'transient';
}
