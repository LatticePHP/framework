<?php

declare(strict_types=1);

namespace Lattice\Contracts\Context;

enum ExecutionType: string
{
    case Http = 'http';
    case Grpc = 'grpc';
    case Message = 'message';
    case Workflow = 'workflow';
    case Job = 'job';
}
